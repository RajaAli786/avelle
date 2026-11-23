<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Support\Shop;
use App\Support\StoreOffers;
class ShopServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer(["market.*", "auth", "account"], function ($view) {
            static $shared = null;
            if ($shared === null) {
                $shared = [
                    "settings" => Shop::settings(),
                    "navCategories" => Shop::categories()->whereIn("id", Shop::activeCategoryIds()),
                    "liveOffers" => StoreOffers::visible(),
                ];
            }
            foreach ($shared as $key => $value) {
                if (!array_key_exists($key, $view->getData())) {
                    $view->with($key, $value);
                }
            }
        });
    }
}
