<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class PurchaseInvoiceController extends Controller
{
    public function index()                  { return $this->stub('Purchase Invoices'); }
    public function create()                 { return $this->stub('Purchase Invoices'); }
    public function store(Request $r)        { return $this->stub('Purchase Invoices'); }
    public function show($id)                { return $this->stub('Purchase Invoices'); }
    public function edit($id)                { return $this->stub('Purchase Invoices'); }
    public function update(Request $r, $id)  { return $this->stub('Purchase Invoices'); }
    public function destroy($id)             { return $this->stub('Purchase Invoices'); }
    public function print($id)               { return $this->stub('Purchase Invoices'); }
    public function getProductInvoices()     { return response()->json([]); }
    private function stub($m) { return redirect()->route('dashboard')->with('error', "$m module not yet installed."); }
}