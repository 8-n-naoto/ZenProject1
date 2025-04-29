<?php

namespace App\Http\Controllers;

class TopController extends Controller
{
    public function index()
    {
        return view("note/top");
    }
}
