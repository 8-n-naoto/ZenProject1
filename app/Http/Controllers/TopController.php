<?php

namespace App\Http\Controllers;

class TopController extends Controller
{
    public function index()
    {
        return view("note/top");
    }
    public function show($memo)
    {
        return view("note/show", compact('memo'));
    }
}
