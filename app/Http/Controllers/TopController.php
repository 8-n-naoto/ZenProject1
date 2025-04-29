<?php

namespace App\Http\Controllers;
use App\Models\Memo;
use Illuminate\Http\Request;

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

    public function store()
    {
        return view("note/store");
    }

    public function create(Memo $memo,Request $request)
    {
        $table = new Memo();
        $table->memo = $request->memo;
        $table->save();

        $memos = Memo::all();
        return view("note/top", compact('memos'));
    }

    public function edit(Memo $memo)
    {
        return view("note/edit", compact('memo'));
    }

    public function update(Memo $memo,Request $request)
    {
        try {
            $table = Memo::find($memo->id);
            $table->memo = $request->memo;
            $table->save();

            // Memo::where('id', $memo->id)->update([
            //     'memo' => $memo->data,
            // ]);

            $is_update = true;
        } catch (\Throwable $th) {
            $is_update = false;
        }

        return view("note/show", compact('memo',"is_update"));
    }

    public function destroy(Memo $memo)
    {
        try {
            $table = Memo::find($memo->id);
            $table->delete();

            $is_delete = true;
        } catch (\Throwable $th) {
            $is_delete = false;
        }

        $memos = Memo::all();
        return view("note/top", compact('memos',"is_delete"));
    }
}
