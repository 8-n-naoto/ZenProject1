<x-app-layout title="まとめて登録" heading="まとめて登録" :back="route('circles.index', $event)">
    <form method="POST" action="{{ route('circles.bulk.store', $event) }}" class="space-y-4">
        @csrf

        <x-card title="書き方" >
            <p class="text-xs text-slate-600">
                1行に <span class="font-semibold">サークル名, 配置, 商品名, 価格</span> をカンマ（またはタブ）区切りで入力します。
                配置・商品名・価格は省略できます。同じサークル名の行は1つのサークルにまとめられます。
            </p>
            <pre class="mt-2 overflow-x-auto rounded-xl bg-slate-50 p-3 text-xs text-slate-600">夏空スタジオ, 東1 ア-12a, 新刊イラスト集, 1500
夏空スタジオ, 東1 ア-12a, アクリルスタンド, 800
ねこまた工房, 東2 ウ-05b
星屑レコード, 西1 サ-31a, 新譜CD, ¥1,200</pre>
        </x-card>

        <x-card>
            <x-textarea name="text" label="登録する内容" rows="12"
                        placeholder="サークル名, 配置, 商品名, 価格" />
        </x-card>

        <x-button class="w-full" size="lg">この内容で登録する</x-button>
    </form>
</x-app-layout>
