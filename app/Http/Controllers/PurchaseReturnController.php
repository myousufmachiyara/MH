<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class PurchaseReturnController extends Controller
{
    public function index()                  { return $this->stub('Purchase Returns'); }
    public function create()                 { return $this->stub('Purchase Returns'); }
    public function store(Request $r)        { return $this->stub('Purchase Returns'); }
    public function show($id)                { return $this->stub('Purchase Returns'); }
    public function edit($id)                { return $this->stub('Purchase Returns'); }
    public function update(Request $r, $id)  { return $this->stub('Purchase Returns'); }
    public function destroy($id)             { return $this->stub('Purchase Returns'); }
    public function print($id)               { return $this->stub('Purchase Returns'); }
    private function stub($m) { return redirect()->route('dashboard')->with('error', "$m module not yet installed."); }
}