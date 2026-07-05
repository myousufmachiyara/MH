<?php

namespace App\Providers;
 
use App\Models\Vendor;
use App\Models\Customer;
use App\Observers\VendorObserver;
use App\Observers\CustomerObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;   // add this

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }
 
    public function boot(): void
    {
        Vendor::observe(VendorObserver::class);
        Customer::observe(CustomerObserver::class);
        Schema::defaultStringLength(191);   // add this line
    }
}