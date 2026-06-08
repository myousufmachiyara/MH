<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class PurchaseOrderController extends Controller
{
    public function index()                  { return $this->stub('Purchase Orders'); }
    public function create()                 { return $this->stub('Purchase Orders'); }
    public function store(Request $r)        { return $this->stub('Purchase Orders'); }
    public function show($id)                { return $this->stub('Purchase Orders'); }
    public function edit($id)                { return $this->stub('Purchase Orders'); }
    public function update(Request $r, $id)  { return $this->stub('Purchase Orders'); }
    public function destroy($id)             { return $this->stub('Purchase Orders'); }
    public function print($id)               { return $this->stub('Purchase Orders'); }
    public function getByCustomer($id)       { return response()->json([]); }
    private function stub($m) { return redirect()->route('dashboard')->with('error', "$m module not yet installed."); }
}