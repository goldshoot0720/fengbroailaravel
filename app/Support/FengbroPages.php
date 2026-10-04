<?php

namespace App\Support;

class FengbroPages
{
    /** @return list<string> */
    public static function allowed(): array
    {
        return [
            'home',
            'subscription',
            'trialpurchase',
            'reinstall',
            'quota',
            'shoppinglist',
            'udemy',
            'food',
            'notes',
            'favorites',
            'images',
            'videos',
            'music',
            'documents',
            'podcast',
            'bank',
            'routine',
            'tools',
            'settings',
            'about',
            'service',
        ];
    }

    /** @return list<string> */
    public static function tools(): array
    {
        return [
            'price',
            'phone',
            'manual',
            'tube',
            'finance',
            'news',
            'image-convert',
            'image-voice',
            'video-merge',
            'yt-bili',
        ];
    }

    /** @return array<string, string> */
    public static function titles(): array
    {
        return [
            'home' => '鋒兄首頁',
            'subscription' => '鋒兄訂閱',
            'trialpurchase' => '鋒兄試用／首購',
            'reinstall' => '鋒兄重灌',
            'quota' => '鋒兄額度',
            'shoppinglist' => '鋒兄購物清單',
            'udemy' => '鋒兄 Udemy',
            'food' => '鋒兄食品 （＋商品庫存）',
            'notes' => '鋒兄筆記',
            'favorites' => '鋒兄常用',
            'images' => '鋒兄圖片',
            'videos' => '鋒兄影片',
            'music' => '鋒兄音樂',
            'documents' => '鋒兄文件',
            'podcast' => '鋒兄播客',
            'bank' => '鋒兄銀行 （＋電子票證/點數）',
            'routine' => '鋒兄例行',
            'tools' => '鋒兄工具 （＋比價）',
            'settings' => '鋒兄設定',
            'about' => '鋒兄關於',
            'service' => '服務資訊',
        ];
    }
}
