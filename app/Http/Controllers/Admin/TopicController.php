<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Topic;
use Illuminate\Http\Request;

class TopicController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->get('q', ''));

        $topics = Topic::query()
            ->withCount('offers') // счётчик привязанных офферов
            ->when($q !== '', fn($qb) => $qb->where('name', 'like', '%' . $q . '%'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.topics.index', compact('topics', 'q'));
    }

    public function create()
    {
        return view('admin.topics.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255','unique:topics,name'],
        ]);

        Topic::create($data);

        return redirect()->route('admin.topics.index')
            ->with('status', 'Тема создана');
    }

    public function edit(Topic $topic)
    {
        return view('admin.topics.edit', compact('topic'));
    }

    public function update(Request $request, Topic $topic)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255','unique:topics,name,' . $topic->id],
        ]);

        $topic->update($data);

        return redirect()->route('admin.topics.index')
            ->with('status', 'Тема обновлена');
    }


    public function destroy(Topic $topic)
    {
        // Запрет удаления, если есть связанные офферы
        if ($topic->offers()->exists()) {
            return back()->with('error', 'Нельзя удалить тему: к ней привязаны офферы.');
        }

        $topic->delete();

        return redirect()
            ->route('admin.topics.index')
            ->with('status', 'Тема удалена');
    }
}
