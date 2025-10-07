<?php

namespace App\Http\Controllers\Wm;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    // Активные офферы
    public function offers()
    {
        $offers = Offer::where('is_active', true)->latest()->paginate(10);
        return view('wm.offers', compact('offers'));
    }

    // Подписаться на оффер
    public function subscribe(Request $request, Offer $offer)
    {
        if (!$offer->is_active) abort(404);

        $sub = Subscription::firstOrCreate(
            ['offer_id' => $offer->id, 'webmaster_id' => $request->user()->id],
            ['cpc' => $offer->cpc, 'token' => Str::random(32), 'is_active' => true]
        );

        return redirect()->route('wm.subs.index')->with('status','Subscription ready');
    }

    // Мои подписки
    public function index(Request $request)
    {
        $subs = Subscription::with('offer')
            ->where('webmaster_id', $request->user()->id)
            ->latest()->paginate(10);

        return view('wm.subs', compact('subs'));
    }
}
