<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AnnouncementService
{
    public const PAGES = ['home', 'login', 'registration', 'member_dashboard', 'admin_dashboard', 'public'];

    public const TYPES = ['photo', 'text', 'combined', 'promotion', 'notice', 'maintenance', 'welcome', 'ticker'];

    public const POSITIONS = ['center', 'top', 'bottom', 'corner', 'banner', 'ticker'];

    public static function sanitize(string $html): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $prior = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prior);
        }
        $clean = function ($node) use (&$clean): string {
            if ($node instanceof \DOMText) {
                return htmlspecialchars($node->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            if (! $node instanceof DOMElement) {
                return '';
            }
            $tag = strtolower($node->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'svg', 'math', 'form', 'input', 'template'], true)) {
                return '';
            }
            $inner = '';
            foreach ($node->childNodes as $child) {
                $inner .= $clean($child);
            }
            if (! in_array($tag, ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a', 'span', 'div', 'font'], true)) {
                return $inner;
            }
            $attrs = '';
            $styles = [];
            if ($tag === 'font') {
                $tag = 'span';
                $color = $node->getAttribute('color');
                if (preg_match('/\A#[0-9a-fA-F]{6}\z/D', $color)) {
                    $styles[] = 'color:'.$color;
                }
            }
            if ($tag === 'a') {
                $href = trim($node->getAttribute('href'));
                if (self::safeUrl($href)) {
                    $attrs .= ' href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'" rel="noopener noreferrer"';
                }
            }
            foreach (explode(';', $node->getAttribute('style')) as $rule) {
                $pair = explode(':', $rule, 2);
                if (count($pair) !== 2) {
                    continue;
                }[$k,$v] = array_map('trim', $pair);
                if ($k === 'text-align' && in_array($v, ['left', 'center', 'right'], true)) {
                    $styles[] = $k.':'.$v;
                }if ($k === 'color' && preg_match('/\A#[0-9a-fA-F]{6}\z/D', $v)) {
                    $styles[] = 'color:'.$v;
                }
            }
            if ($styles) {
                $attrs .= ' style="'.implode(';', $styles).'"';
            }

            return '<'.$tag.$attrs.'>'.$inner.($tag === 'br' ? '' : '</'.$tag.'>');
        };
        $result = '';
        foreach ($dom->getElementsByTagName('body')->item(0)?->childNodes ?? [] as $child) {
            $result .= $clean($child);
        }

        return $result;
    }

    public static function safeUrl(?string $url): bool
    {
        return is_string($url) && ! preg_match('/[\x00-\x20\x7f\\\\]/', $url) && ((str_starts_with($url, '/') && ! str_starts_with($url, '//')) || filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true));
    }

    public static function page(Request $request): ?string
    {
        return match ($request->route()?->getName()) {
            'register.form' => 'login','member.dashboard' => 'member_dashboard','admin.dashboard' => 'admin_dashboard',default => $request->path() === '/' ? 'home' : null
        };
    }

    public static function viewer(Request $request): string
    {
        return hash_hmac('sha256', $request->user() ? 'user:'.$request->user()->id : 'session:'.$request->session()->getId(), config('app.key'));
    }

    public static function window(object $a, Request $r): string
    {
        return match ($a->frequency) {
            'daily' => now()->toDateString(),'once' => 'once','visit' => hash('sha256', (string) $r->attributes->get('announcement_visit')) ,default => hash('sha256', $r->session()->getId())
        };
    }

    public static function eligible(object $a, Request $r, ?string $page): bool
    {
        if (! $page || $a->status !== 'published' || ($a->starts_at && now()->lt($a->starts_at)) || ($a->ends_at && now()->gte($a->ends_at))) {
            return false;
        }
        $u = $r->user();
        $member = $u && $u->role === 'member' && ! $u->is_blocked && $u->deleted_at === null;
        $admin = $u && in_array($u->role, ['admin', 'super_admin'], true) && $u->status === 'active' && ! $u->is_blocked && $u->deleted_at === null;
        if (! match ($a->audience) {
            'all' => true,'guests' => ! $u,'members' => $member,'admins' => $admin,'group' => $member && $u->status === $a->member_group,default => false
        }) {
            return false;
        }
        $pages = json_decode($a->pages, true) ?? [];
        $match = in_array($page, $pages, true) || ($page === 'login' && in_array('registration', $pages, true)) || (! $u && in_array($page, ['home', 'login'], true) && in_array('public', $pages, true));

        return $match && ($a->frequency !== 'after_login' || in_array($page, ['member_dashboard', 'admin_dashboard'], true));
    }

    public static function ready(): bool
    {
        return Schema::hasTable('announcements');
    }

    public static function forPage(Request $r): array
    {
        if (! self::ready()) {
            return [];
        }$page = self::page($r);
        if (! $page) {
            return [];
        }
        $result = [];
        $viewer = self::viewer($r);
        $r->attributes->set('announcement_visit', bin2hex(random_bytes(12)));
        foreach (DB::table('announcements')->where('status', 'published')->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'important' THEN 1 ELSE 2 END")->orderByDesc('id')->get() as $a) {
            if (! self::eligible($a, $r, $page)) {
                continue;
            }$window = self::window($a, $r);
            if (DB::table('announcement_events')->where(['announcement_id' => $a->id, 'revision' => $a->revision, 'viewer_hash' => $viewer, 'window_key' => $window])->exists()) {
                continue;
            }
            $token = hash_hmac('sha256', implode('|', [$a->id, $a->revision, $viewer, $window, $page]), config('app.key'));
            $result[] = ['id' => $a->id, 'revision' => $a->revision, 'title' => $a->title, 'heading' => $a->heading, 'subheading' => $a->subheading, 'content' => $a->content, 'position' => $a->position, 'button_text' => $a->button_text, 'button_url' => $a->button_url, 'image_alt' => $a->image_alt, 'image' => $a->image_base64 ? 'data:'.$a->image_mime.';base64,'.$a->image_base64 : null, 'delay' => $a->delay_seconds, 'window' => $window, 'page' => $page, 'token' => $token];
        }

        return $result;
    }

    public static function audit(int $actor, ?int $id, string $action, ?object $before, ?object $after): void
    {
        $safe = static function (?object $row): ?string {
            if (! $row) {
                return null;
            }$data = (array) $row;
            unset($data['image_base64']);

            return json_encode($data, JSON_THROW_ON_ERROR);
        };
        DB::table('announcement_audits')->insert(['actor_id' => $actor, 'announcement_id' => $id, 'action' => $action, 'before_json' => $safe($before), 'after_json' => $safe($after), 'created_at' => now()]);
    }
}
