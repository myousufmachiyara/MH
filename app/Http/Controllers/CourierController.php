<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class CourierController extends Controller
{
    public function index()                  { return $this->stub('Couriers'); }
    public function create()                 { return $this->stub('Couriers'); }
    public function store(Request $r)        { return $this->stub('Couriers'); }
    public function show($id)                { return $this->stub('Couriers'); }
    public function edit($id)                { return $this->stub('Couriers'); }
    public function update(Request $r, $id)  { return $this->stub('Couriers'); }
    public function destroy($id)             { return $this->stub('Couriers'); }
    public function print($id)               { return $this->stub('Couriers'); }
    private function stub($m) { return redirect()->route('dashboard')->with('error', "$m module not yet installed."); }
}