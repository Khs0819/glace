<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Event;
use App\Models\HeroSlide;
use App\Models\HomeAbout;
use App\Models\HomeWhyGlace;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $slides   = HeroSlide::orderBy('sort_order')->get();
        $about    = HomeAbout::first();
        $why      = HomeWhyGlace::first();
        $branches = Branch::orderBy('sort_order')->get();
        $events   = Event::with('images')->latest('id')->take(3)->get();

        return response()->json([
            'hero' => [
                'slides' => $slides->map(fn ($s) => [
                    'manImg'        => $s->man_img,
                    'pieceImg'      => $s->piece_img,
                    'zigzagsImg'    => $s->zigzags_img,
                    'titleH1'       => $s->title_h1,
                    'titleH2'       => $s->title_h2,
                    'bgColor'       => $s->bg_color,
                    'headerBgColor' => $s->header_bg_color,
                    'h1BgColor'     => $s->h1_bg_color,
                    'h2BgColor'     => $s->h2_bg_color,
                ]),
            ],
            'about' => $about ? [
                'title'      => $about->title,
                'paragraphs' => $about->paragraphs,
                'image'      => $about->image,
                'ctaLabel'   => $about->cta_label,
                'ctaHref'    => $about->cta_href,
            ] : null,
            'whyGlace' => $why ? [
                'title'          => $why->title,
                'description'    => $why->description,
                'features'       => $why->features,
                'videoUrl'       => $why->video_url,
                'videoThumbnail' => $why->video_thumbnail,
            ] : null,
            'branches' => [
                'title'    => 'ننتظركم في المواعيد التالية',
                'branches' => $branches->map(fn ($b) => [
                    'id'           => $b->id,
                    'label'        => $b->label,
                    'mapSrc'       => $b->map_src,
                    'address'      => $b->address,
                    'phone'        => $b->phone,
                    'whatsapp'     => $b->whatsapp,
                    'weekdayHours' => $b->weekday_hours,
                    'fridayHours'  => $b->friday_hours,
                    'borderRadius' => $b->border_radius,
                ]),
            ],
            'events' => [
                'title'     => 'الفعاليات و المناسبات',
                'moreLabel' => 'عرض المزيد',
                'moreHref'  => '/events',
                'items'     => $events->map(fn ($e) => [
                    'id'    => $e->id,
                    'title' => $e->title,
                    'image' => $e->list_image,
                    'href'  => '/events/' . $e->id,
                ]),
            ],
        ]);
    }
}
