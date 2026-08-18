<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemoRequest;
use App\Http\Requests\UpdateMemoRequest;
use App\Models\Memo;

class TopController extends Controller
{
    public function index()
    {
        $memos = Memo::latest()->get();

        return view('note.top', compact('memos'));
    }

    public function show(Memo $memo)
    {
        return view('note.show', compact('memo'));
    }

    public function store()
    {
        return view('note.store');
    }

    public function create(StoreMemoRequest $request)
    {
        Memo::create($request->validated());

        return redirect()->route('top')->with('status', 'メモを作成しました。');
    }

    public function edit(Memo $memo)
    {
        return view('note.edit', compact('memo'));
    }

    public function update(UpdateMemoRequest $request, Memo $memo)
    {
        $memo->update($request->validated());

        return redirect()->route('show', $memo)->with('status', 'メモを更新しました。');
    }

    public function destroy(Memo $memo)
    {
        $memo->delete();

        return redirect()->route('top')->with('status', 'メモを削除しました。');
    }
}
