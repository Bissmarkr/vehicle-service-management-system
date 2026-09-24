<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class ApiResourceController extends Controller
{
    public function index() { return response()->json(['data' => []]); }
    public function store(Request $request) { return response()->json(['data' => $request->all()], 201); }
    public function show(string $id) { return response()->json(['data' => ['id' => $id]]); }
    public function update(Request $request, string $id) { return response()->json(['data' => array_merge(['id' => $id], $request->all())]); }
    public function destroy(string $id) { return response()->noContent(); }
}
