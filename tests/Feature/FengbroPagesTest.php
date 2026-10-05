<?php

namespace Tests\Feature;

use App\Http\Middleware\AcceptFengbroCsrf;
use App\Livewire\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FengbroPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(AcceptFengbroCsrf::class);
        $sql = (string) file_get_contents(base_path('database/schema/fengbro_sqlite.sql'));
        foreach (preg_split('/;\s*\n/', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                DB::unprepared($statement);
            }
        }
    }

    public function test_every_allowed_page_renders_through_livewire(): void
    {
        $pages = [
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

        $root = $this->get('/');
        $root->assertOk();
        $root->assertSee('鋒兄首頁', false);
        $root->assertSee('const serverInitialFull = false', false);
        $this->assertPageUsesLivewire('home');

        foreach ($pages as $page => $title) {
            $response = $this->get('/index.php?page='.$page);
            $response->assertOk();
            $response->assertSee($title, false);
            $this->assertPageUsesLivewire($page);
        }

        $dashboard = $this->get('/index.php?page=dashboard');
        $dashboard->assertOk();
        $dashboard->assertSee('鋒兄首頁', false);
        $dashboard->assertSee('const serverInitialFull = true', false);
        $this->assertPageUsesLivewire('home');
    }

    public function test_page_writes_show_up_on_the_next_read(): void
    {
        $rows = [
            'subscription' => ['table' => 'subscription', 'payload' => ['name' => '驗收訂閱'], 'see' => '驗收訂閱'],
            'trialpurchase' => ['table' => 'trialpurchase', 'payload' => ['name' => '驗收試用'], 'see' => '驗收試用'],
            'reinstall' => ['table' => 'reinstall', 'payload' => ['name' => '驗收重灌'], 'see' => '驗收重灌'],
            'quota' => ['table' => 'quota', 'payload' => ['name' => '驗收額度'], 'see' => '驗收額度'],
            'shoppinglist' => ['table' => 'shoppinglist', 'payload' => ['name' => '驗收購物'], 'see' => '驗收購物'],
            'udemy' => ['table' => 'udemy', 'payload' => ['name' => '驗收課程'], 'see' => '驗收課程'],
            'food' => ['table' => 'food', 'payload' => ['name' => '驗收食品'], 'see' => '驗收食品'],
            'notes' => ['table' => 'article', 'payload' => ['title' => '驗收筆記'], 'see' => '驗收筆記'],
            'favorites' => ['table' => 'commonaccount', 'payload' => ['name' => '驗收常用'], 'see' => '驗收常用'],
            'bank' => ['table' => 'bank', 'payload' => ['name' => '驗收銀行'], 'see' => '驗收銀行'],
            'routine' => ['table' => 'routine', 'payload' => ['name' => '驗收例行'], 'see' => '驗收例行'],
            'images' => ['table' => 'image', 'payload' => ['name' => '驗收圖片'], 'see' => '驗收圖片'],
            'videos' => ['table' => 'video', 'payload' => ['name' => '驗收影片'], 'see' => '驗收影片'],
            'music' => ['table' => 'music', 'payload' => ['name' => '驗收音樂'], 'see' => '驗收音樂'],
            'documents' => ['table' => 'commondocument', 'payload' => ['name' => '驗收文件'], 'see' => '驗收文件'],
            'podcast' => ['table' => 'podcast', 'payload' => ['name' => '驗收播客'], 'see' => '驗收播客'],
        ];

        foreach ($rows as $page => $row) {
            $created = $this->postJson('/index.php?action=create&table='.$row['table'], $row['payload']);
            $created->assertOk();
            $created->assertJson(['success' => true]);

            $again = $this->get('/index.php?page='.$page);
            $again->assertOk();
            $again->assertSee($row['see'], false);
        }

        $password = $this->post('/index.php?page=settings', [
            'notif_password_action' => 'set',
            'notif_password_new' => 'pass1234',
        ]);
        $password->assertOk();
        $password->assertSee('通知密碼已建立', false);
        $this->get('/index.php?page=settings')->assertOk()->assertSee('更新密碼', false);

        $manual = $this->postJson('/index.php?fengbro_manual=1', [
            'name' => '驗收手動價格',
            'currency' => 'TWD',
        ]);
        $manual->assertOk();
        $manual->assertJsonFragment(['name' => '驗收手動價格']);
        $this->get('/index.php?page=tools&tool=manual')
            ->assertOk()
            ->assertSee('驗收手動價格', false);
    }

    public function test_delete_and_empty_trash_use_index_and_reject_tokenless_get(): void
    {
        $page = $this->get('/index.php?page=subscription&trash=1');
        $page->assertOk();
        $page->assertSee('index.php?action=delete', false);
        $page->assertSee('index.php?action=empty_trash', false);
        $page->assertDontSee('api.php?action=delete', false);
        $page->assertDontSee('api.php?action=empty_trash', false);
        $page->assertDontSee('api.php?action=restore', false);

        $created = $this->postJson('/index.php?action=create&table=food', ['name' => '驗收刪除食品']);
        $created->assertOk();
        $created->assertJson(['success' => true]);
        $id = (string) $created->json('id');
        $this->assertNotSame('', $id);

        $blocked = $this->get('/index.php?action=delete&table=food&id='.$id);
        $blocked->assertStatus(419);
        $this->assertSame(1, DB::table('food')->where('id', $id)->count());
        $this->get('/index.php?page=food')->assertOk()->assertSee('驗收刪除食品', false);

        $_SESSION['csrf_token'] = 'feature-delete-token';
        $deleted = $this->withHeader('X-CSRF-TOKEN', 'feature-delete-token')
            ->get('/index.php?action=delete&table=food&id='.$id);
        $deleted->assertOk();
        $deleted->assertJson(['success' => true]);
        $this->assertSame(0, DB::table('food')->where('id', $id)->count());
        $this->get('/index.php?page=food')->assertOk()->assertDontSee('驗收刪除食品', false);

        $this->flushHeaders();
        unset($_SESSION['csrf_token']);

        $manual = $this->postJson('/index.php?fengbro_manual=1', [
            'name' => '驗收手動刪除',
            'currency' => 'TWD',
        ]);
        $manual->assertOk();
        $manualId = (string) $manual->json('id');
        $this->assertNotSame('', $manualId);
        $manualBlocked = $this->get('/index.php?fengbro_manual=1&action=delete&id='.$manualId);
        $manualBlocked->assertStatus(419);
        $this->assertSame(1, DB::table('manualprice')->where('id', $manualId)->count());
    }

    private function assertPageUsesLivewire(string $page): void
    {
        $route = Route::getRoutes()->match(
            \Illuminate\Http\Request::create('/index.php', 'GET', ['page' => $page])
        );
        $uses = (string) ($route->getAction('uses') ?? '');
        $this->assertStringStartsWith(Workspace::class, $uses);
        $workspace = (string) file_get_contents(base_path('app/Livewire/Workspace.php'));
        $this->assertStringNotContainsString("pages/", $workspace);
        $this->assertStringNotContainsString('legacy_index.php', $workspace);
        $this->assertFileExists(base_path('resources/views/fengbro/'.$page.'.php'));
    }
}
