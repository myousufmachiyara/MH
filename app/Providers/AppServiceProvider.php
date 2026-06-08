<?php

namespace App\Providers;
 
use App\Models\Vendor;
use App\Models\Customer;
use App\Observers\VendorObserver;
use App\Observers\CustomerObserver;
use Illuminate\Support\ServiceProvider;
 
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
    }
}