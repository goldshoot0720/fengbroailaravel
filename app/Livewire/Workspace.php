<?php

namespace App\Livewire;

use App\Support\FengbroPages;
use App\Support\LegacyBridge;
use Livewire\Component;

class Workspace extends Component
{
    public function render()
    {
        LegacyBridge::boot();

        $page = (string) request()->query('page', 'home');
        $homeInitialFullView = false;
        if ($page === 'dashboard') {
            $page = 'home';
            $homeInitialFullView = true;
        }
        if (! in_array($page, FengbroPages::allowed(), true)) {
            $page = 'home';
        }

        $requestedTool = (string) request()->query('tool', '');
        $bodyDataTool = $page === 'tools' && in_array($requestedTool, FengbroPages::tools(), true)
            ? $requestedTool
            : '';
        $service = (string) request()->query('service', '');

        $_GET['page'] = $page;
        if ($bodyDataTool !== '') {
            $_GET['tool'] = $bodyDataTool;
        } else {
            unset($_GET['tool']);
        }
        if ($service !== '') {
            $_GET['service'] = $service;
        }

        $resolvedTitle = FengbroPages::titles()[$page] ?? '鋒兄首頁';
        $view = 'fengbro.'.$page;

        $this->scheduleIndexMaintenance();

        if (! view()->exists($view)) {
            return view('livewire.workspace', [
                'html' => '<div class="content-body"><p>頁面不存在</p></div>',
            ])->layout('layouts.app', [
                'title' => $resolvedTitle,
                'page' => $page,
                'tool' => $bodyDataTool,
            ]);
        }

        $html = view($view, [
            'homeInitialFullView' => $homeInitialFullView,
            'page' => $page,
            'serviceKey' => $service,
        ])->render();

        return view('livewire.workspace', [
            'html' => $html,
        ])->layout('layouts.app', [
            'title' => $resolvedTitle,
            'page' => $page,
            'tool' => $bodyDataTool,
        ]);
    }

    private function scheduleIndexMaintenance(): void
    {
        static $scheduled = false;
        if ($scheduled) {
            return;
        }
        $scheduled = true;

        register_shutdown_function(static function () {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            try {
                if (function_exists('fengbroEnsurePerformanceIndexes')) {
                    fengbroEnsurePerformanceIndexes();
                }
            } catch (\Throwable $e) {
                error_log('fengbro index check failed: '.$e->getMessage());
            }
        });
    }
}
