<?php

namespace App\Http\Controllers;
use App\Models\Memo;

class TopController extends Controller
{
    public function index()
    {
        $memos = Memo::all();
        return view("note/top",compact("memos"));
    }
    public function show(Memo $memo)
    {
        return view("note/show", compact('memo'));
    }
}
