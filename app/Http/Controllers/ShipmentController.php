<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class ShipmentController extends Controller
{
    public function index()                  { return $this->stub('Shipments'); }
    public function create()                 { return $this->stub('Shipments'); }
    public function store(Request $r)        { return $this->stub('Shipments'); }
    public function show($id)                { return $this->stub('Shipments'); }
    public function edit($id)                { return $this->stub('Shipments'); }
    public function update(Request $r, $id)  { return $this->stub('Shipments'); }
    public function destroy($id)             { return $this->stub('Shipments'); }
    public function print($id)               { return $this->stub('Shipments'); }
    private function stub($m) { return redirect()->route('dashboard')->with('error', "$m module not yet installed."); }
}