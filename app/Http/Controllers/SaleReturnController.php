<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class SaleReturnController extends Controller
{
    public function index()                  { return $this->stub('Sale Returns'); }
    public function create()                 { return $this->stub('Sale Returns'); }
    public function store(Request $r)        { return $this->stub('Sale Returns'); }
    public function show($id)                { return $this->stub('Sale Returns'); }
    public function edit($id)                { return $this->stub('Sale Returns'); }
    public function update(Request $r, $id)  { return $this->stub('Sale Returns'); }
    public function destroy($id)             { return $this->stub('Sale Returns'); }
    public function print($id)               { return $this->stub('Sale Returns'); }
    private function stub($m) { return redirect()->route('dashboard')->with('error', "$m module not yet installed."); }
}