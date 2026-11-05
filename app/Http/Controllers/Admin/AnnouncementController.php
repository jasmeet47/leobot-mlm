<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnnouncementService as Announcements;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AnnouncementController extends Controller
{
    private function assertActor(Request $request): void
    {
        $actor = DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
        abort_unless($actor && in_array($actor->role, ['admin', 'super_admin'], true) && $actor->status === 'active' && ! $actor->is_blocked && $actor->deleted_at === null, 403);
    }

    public function index(Request $request): View
    {
        $rows = DB::table('announcements')->orderByDesc('id')->get();
        $stats = DB::table('announcement_events')->selectRaw('announcement_id, count(*) as views, count(clicked_at) as clicks')->groupBy('announcement_id')->get()->keyBy('announcement_id');

        return view('admin.announcements', ['announcements' => $rows, 'stats' => $stats, 'editing' => $request->integer('edit') ? DB::table('announcements')->find($request->integer('edit')) : null, 'uploadLimit' => DB::table('announcement_settings')->where('id', 1)->value('upload_limit_kb') ?? 2048]);
    }

    private function data(Request $request, ?object $existing): array
    {
        $limit = DB::table('announcement_settings')->where('id', 1)->value('upload_limit_kb') ?? 2048;
        $d = $request->validate([
            'title' => ['required', 'string', 'max:160'], 'heading' => ['nullable', 'string', 'max:200'], 'subheading' => ['nullable', 'string', 'max:250'], 'content' => ['nullable', 'string', 'max:20000'],
            'type' => ['required', Rule::in(Announcements::TYPES)], 'position' => ['required', Rule::in(Announcements::POSITIONS)], 'audience' => ['required', Rule::in(['all', 'guests', 'members', 'admins', 'group'])],
            'pages' => ['required', 'array', 'min:1'], 'pages.*' => ['required', Rule::in(Announcements::PAGES)], 'member_group' => ['nullable', Rule::in(['active', 'inactive'])],
            'button_text' => ['nullable', 'string', 'max:80'], 'button_url' => ['nullable', 'string', 'max:2048'], 'image_alt' => ['nullable', 'string', 'max:200'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$limit, 'dimensions:max_width=6000,max_height=6000'], 'remove_image' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'published', 'paused', 'archived'])], 'priority' => ['required', Rule::in(['normal', 'important', 'urgent'])],
            'frequency' => ['required', Rule::in(['visit', 'session', 'daily', 'once', 'after_login'])], 'delay_seconds' => ['required', 'integer', 'between:0,120'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        if (($d['button_url'] ?? null) && ! Announcements::safeUrl($d['button_url'])) {
            throw ValidationException::withMessages(['button_url' => 'Use a safe HTTPS/HTTP URL or local /path.']);
        }
        if (($d['button_text'] ?? null) && ! ($d['button_url'] ?? null)) {
            throw ValidationException::withMessages(['button_url' => 'A button link is required with button text.']);
        }
        if ($d['audience'] === 'group' && ! ($d['member_group'] ?? null)) {
            throw ValidationException::withMessages(['member_group' => 'Choose a member group.']);
        }
        $d['content'] = Announcements::sanitize($d['content'] ?? '');
        $d['image_base64'] = $existing?->image_base64;
        $d['image_mime'] = $existing?->image_mime;
        if ($request->boolean('remove_image')) {
            $d['image_base64'] = null;
            $d['image_mime'] = null;
        }
        if ($file = $request->file('image')) {
            $bytes = file_get_contents($file->getRealPath());
            $info = getimagesizefromstring($bytes);
            if (! $info || $info[0] * $info[1] > 16000000) {
                throw ValidationException::withMessages(['image' => 'Image exceeds the safe 16-megapixel limit.']);
            }
            $image = imagecreatefromstring($bytes);
            if (! $image) {
                throw ValidationException::withMessages(['image' => 'Image upload failed.']);
            }
            $scale = min(1, 1600 / max(imagesx($image), imagesy($image)));
            $canvas = imagescale($image, max(1, (int) (imagesx($image) * $scale)), max(1, (int) (imagesy($image) * $scale)));
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            ob_start();
            $ok = imagewebp($canvas, null, 85);
            $optimized = ob_get_clean();
            imagedestroy($canvas);
            imagedestroy($image);
            if (! $ok || ! $optimized) {
                throw ValidationException::withMessages(['image' => 'Image optimization failed.']);
            }$d['image_base64'] = base64_encode($optimized);
            $d['image_mime'] = 'image/webp';
        }
        if (in_array($d['type'], ['photo', 'combined'], true) && ! $d['image_base64']) {
            throw ValidationException::withMessages(['image' => 'This popup type requires an image.']);
        }
        if ($d['image_base64'] && ! trim($d['image_alt'] ?? '')) {
            throw ValidationException::withMessages(['image_alt' => 'Describe the image for screen readers.']);
        }
        if ($d['type'] !== 'photo' && ! trim(strip_tags($d['content'])) && ! trim($d['heading'] ?? '')) {
            throw ValidationException::withMessages(['content' => 'Add a heading or message.']);
        }
        $d['pages'] = json_encode(array_values(array_unique($d['pages'])), JSON_THROW_ON_ERROR);
        foreach (['starts_at', 'ends_at'] as $key) {
            $d[$key] = empty($d[$key]) ? null : Carbon::parse($d[$key], 'Asia/Kolkata')->utc();
        }
        if ($d['status'] === 'published' && ($d['ends_at'] ?? null) && now()->gte($d['ends_at'])) {
            throw ValidationException::withMessages(['ends_at' => 'Cannot publish an already expired announcement.']);
        }
        unset($d['image'],$d['remove_image']);

        return $d;
    }

    public function save(Request $request, ?int $id = null): RedirectResponse
    {
        $existing = $id ? DB::table('announcements')->find($id) : null;
        abort_if($id && ! $existing, 404);
        $data = $this->data($request, $existing);
        DB::transaction(function () use ($request, $id, $data): void {
            $this->assertActor($request);
            $before = $id ? DB::table('announcements')->where('id', $id)->lockForUpdate()->first() : null;
            if ($id) {
                abort_unless($before, 404);
                abort_unless((int) $request->input('revision') === $before->revision, 409, 'Announcement changed. Refresh before saving.');
            }
            $values = $data + ['updated_by' => $request->user()->id, 'updated_at' => now(), 'revision' => ($before->revision ?? 0) + 1];
            if ($id) {
                DB::table('announcements')->where('id', $id)->update($values);
                $newId = $id;
            } else {
                $newId = DB::table('announcements')->insertGetId($values + ['created_at' => now()]);
            }
            Announcements::audit($request->user()->id, $newId, $before ? 'updated' : 'created', $before, DB::table('announcements')->find($newId));
        });

        return redirect()->route('admin.announcements.index')->with('success', $id ? 'Announcement updated successfully.' : 'Popup created successfully.');
    }

    public function action(Request $request, int $id): RedirectResponse
    {
        $d = $request->validate(['action' => ['required', Rule::in(['publish', 'pause', 'resume', 'archive', 'delete'])], 'revision' => ['required', 'integer']]);
        DB::transaction(function () use ($request, $id, $d): void {
            $this->assertActor($request);
            $a = DB::table('announcements')->where('id', $id)->lockForUpdate()->first();
            abort_unless($a, 404);
            abort_unless($a->revision === (int) $d['revision'], 409, 'Refresh before changing this announcement.');
            if (in_array($d['action'], ['publish', 'resume'], true)) {
                if ($a->ends_at && now()->gte($a->ends_at)) {
                    throw ValidationException::withMessages(['schedule' => 'Invalid schedule date. Edit expiry before publishing.']);
                }
                if (in_array($a->type, ['photo', 'combined'], true) && ! $a->image_base64) {
                    throw ValidationException::withMessages(['image' => 'Image required.']);
                }
            }
            if ($d['action'] === 'delete') {
                Announcements::audit($request->user()->id, $id, 'deleted', $a, null);
                DB::table('announcements')->where('id', $id)->delete();

                return;
            }
            $status = match ($d['action']) {
                'publish','resume' => 'published','pause' => 'paused',default => 'archived'
            };
            DB::table('announcements')->where('id', $id)->update(['status' => $status, 'revision' => $a->revision + 1, 'updated_by' => $request->user()->id, 'updated_at' => now()]);
            Announcements::audit($request->user()->id, $id, $d['action'], $a, DB::table('announcements')->find($id));
        });

        return back()->with('success', match ($d['action']) {
            'pause' => 'Popup disabled successfully.','publish','resume' => 'Popup published successfully.',default => 'Announcement '.$d['action'].' completed successfully.'
        });
    }

    public function settings(Request $request): RedirectResponse
    {
        $d = $request->validate(['upload_limit_kb' => ['required', 'integer', 'between:128,5120']]);
        DB::transaction(function () use ($request, $d): void {
            $this->assertActor($request);
            $before = DB::table('announcement_settings')->where('id', 1)->lockForUpdate()->first();
            DB::table('announcement_settings')->updateOrInsert(['id' => 1], $d + ['updated_at' => now(), 'created_at' => $before?->created_at ?? now()]);
            Announcements::audit($request->user()->id, null, 'upload_limit', $before, DB::table('announcement_settings')->find(1));
        });

        return back()->with('success', 'Image upload limit saved successfully.');
    }

    public function event(Request $r, int $id): JsonResponse
    {
        $d = $r->validate(['revision' => ['required', 'integer'], 'page' => ['required', Rule::in(Announcements::PAGES)], 'window' => ['required', 'string', 'max:80'], 'token' => ['required', 'string', 'size:64'], 'event' => ['required', Rule::in(['view', 'click'])]]);
        $viewer = Announcements::viewer($r);
        $token = hash_hmac('sha256', implode('|', [$id, $d['revision'], $viewer, $d['window'], $d['page']]), config('app.key'));
        abort_unless(hash_equals($token, $d['token']), 403);
        DB::transaction(function () use ($id, $r, $d, $viewer): void {
            $a = DB::table('announcements')->where('id', $id)->lockForUpdate()->first();
            abort_unless($a && $a->revision === (int) $d['revision'] && Announcements::eligible($a, $r, $d['page']), 404);
            $key = ['announcement_id' => $id, 'revision' => $d['revision'], 'viewer_hash' => $viewer, 'window_key' => $d['window']];
            if ($d['event'] === 'view') {
                DB::table('announcement_events')->insertOrIgnore($key + ['viewed_at' => now()]);
            } else {
                DB::table('announcement_events')->where($key)->whereNull('clicked_at')->update(['clicked_at' => now()]);
            }
        });

        return response()->json(['ok' => true]);
    }
}
