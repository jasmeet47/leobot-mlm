<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BrandingController extends Controller
{
    public function index(): View
    {
        return view('admin.logo-settings', ['storageReady' => Schema::hasTable('branding_settings')]);
    }

    public function logo(Request $request): Response
    {
        $revision = Schema::hasTable('branding_settings') ? DB::table('branding_settings')->orderByDesc('id')->first() : null;
        $bytes = $revision ? base64_decode($revision->image_base64, true) : file_get_contents(public_path('branding/lumiflowon-original-logo'));
        abort_unless(is_string($bytes), 503);
        $mime = $revision?->mime_type ?? (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true), 503);
        $response = response($bytes, 200, ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox", 'Cache-Control' => 'public, max-age=0, must-revalidate']);
        $response->setEtag(hash('sha256', $bytes));
        $response->isNotModified($request);

        return $response;
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('branding_settings'), 503);
        $request->validate(['logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=2048,max_height=2048']]);
        $file = $request->file('logo');
        $bytes = $file->get();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true), 422);
        DB::transaction(function () use ($request, $bytes, $mime): void {
            $admin = DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            abort_unless($admin && $admin->deleted_at === null && ! $admin->is_blocked && $admin->status === 'active'
                && in_array($admin->role, ['admin', 'super_admin'], true), 403);
            DB::table('branding_settings')->insert(['image_base64' => base64_encode($bytes), 'mime_type' => $mime,
                'uploaded_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('logo_status', 'Logo saved. Previous revisions are retained.');
    }
}
