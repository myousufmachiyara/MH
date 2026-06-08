<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class SaleInvoiceController extends Controller
{
    public function index()                  { return $this->stub('Sale Invoices'); }
    public function create()                 { return $this->stub('Sale Invoices'); }
    public function store(Request $r)        { return $this->stub('Sale Invoices'); }
    public function show($id)                { return $this->stub('Sale Invoices'); }
    public function edit($id)                { return $this->stub('Sale Invoices'); }
    public function update(Request $r, $id)  { return $this->stub('Sale Invoices'); }
    public function destroy($id)             { return $this->stub('Sale Invoices'); }
    public function print($id)               { return $this->stub('Sale Invoices'); }
    private function stub($m) { return redirect()->route('dashboard')->with('error', "$m module not yet installed."); }
}