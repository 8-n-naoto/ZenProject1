<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| スケジュール実行
|--------------------------------------------------------------------------
|
| サーバー側で以下を1つ登録しておくと、下記のスケジュールが動きます。
|
|   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// 開催日を迎えたイベントを自動で「開催中」にし、未登録の購入結果を担当者に通知する
Schedule::command('events:advance-scheduled')
    ->hourly()
    ->withoutOverlapping();
