<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\BrandController;
use App\Http\Controllers\CatalogAdminController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\MarketplaceController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function check($condition, string $label): void
{
    if (!$condition) throw new RuntimeException($label);
    echo "PASS $label\n";
}
function req(array $data = [], string $uri = '/admin/brands', string $method = 'POST'): Request
{
    $request = Request::create($uri, $method, $data);
    $request->setLaravelSession(app('session')->driver());
    $request->setUserResolver(fn () => Auth::user());
    app()->instance('request', $request);
    app('url')->setRequest($request);
    return $request;
}
function rejects(callable $callback, string $label): void
{
    try { $callback(); } catch (ValidationException $exception) { check(true, $label); return; }
    throw new RuntimeException('Expected rejection: ' . $label);
}
function denied(callable $callback, string $label): void
{
    try { $callback(); } catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        check($exception->getStatusCode() === 403, $label); return;
    }
    throw new RuntimeException('Expected access rejection: ' . $label);
}

config(['session.driver' => 'array']);
app('session')->forgetDrivers();
Auth::forgetGuards();
app('session')->driver()->start();
view()->share('errors', new Illuminate\Support\ViewErrorBag);
$files = [];
DB::beginTransaction();
try {
    $suffix = uniqid();
    $admin = User::create(['name' => 'Brand QA', 'email' => 'brand-admin-' . $suffix . '@example.test', 'password' => 'QaBrand!123456']);
    $admin->forceFill(['role' => 'admin'])->save();
    $seller = User::create(['name' => 'Brand Seller QA', 'email' => 'brand-seller-' . $suffix . '@example.test', 'password' => 'QaBrand!123456']);
    $seller->forceFill(['role' => 'seller'])->save();
    $sellerId = DB::table('sellers')->insertGetId([
        'user_id' => $seller->id, 'shop_name' => 'Brand QA shop', 'slug' => 'brand-qa-' . $suffix,
        'phone' => '9876543210', 'pickup_address' => 'QA pickup address', 'city' => 'Delhi',
        'state' => 'Delhi', 'pincode' => '110001', 'status' => 'approved', 'commission_rate' => 10,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $controller = new BrandController;
    $data = ['name' => 'QA Brand ' . $suffix, 'description' => 'Brand test description', 'sort_order' => 2, 'active' => 1];
    Auth::logout();
    denied(fn () => $controller->save(req($data)), 'Guest cannot manage brands');
    Auth::login($seller);
    denied(fn () => $controller->save(req($data)), 'Seller cannot create master brands');
    Auth::login($admin);
    $r = req($data);
    $tmp = tempnam(sys_get_temp_dir(), 'avelle-brand'); $files[] = $tmp;
    file_put_contents($tmp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7l8AAAAASUVORK5CYII='));
    $r->files->set('logo', new Illuminate\Http\UploadedFile($tmp, 'brand.png', 'image/png', null, true));
    $controller->save($r);
    $brand = DB::table('brands')->where('name', $data['name'])->firstOrFail();
    $files[] = public_path($brand->logo);
    check($brand->active && is_file(public_path($brand->logo)), 'Admin creates a brand with a logo');
    rejects(fn () => $controller->save(req($data)), 'Duplicate brand name is rejected');
    rejects(fn () => $controller->save(req(array_merge($data, ['name' => 'Other QA', 'slug' => $brand->slug]))), 'Duplicate brand slug is rejected');
    $bad = req(array_merge($data, ['name' => 'Bad file ' . $suffix]));
    $badTmp = tempnam(sys_get_temp_dir(), 'avelle-brand'); $files[] = $badTmp;
    file_put_contents($badTmp, '<?php echo "invalid image";');
    $bad->files->set('logo', new Illuminate\Http\UploadedFile($badTmp, 'logo.php', 'application/x-php', null, true));
    rejects(fn () => $controller->save($bad), 'Executable files cannot be uploaded as brand logos');

    $category = DB::table('categories')->firstOrFail();
    $productData = [
        'name' => 'QA Branded Product ' . $suffix, 'category_id' => $category->id, 'brand_id' => $brand->id,
        'audience' => 'women', 'description' => 'A detailed product description for the brand verification.',
        'price' => 500, 'old' => 700, 'color' => 'Pink', 'sizes' => 'S, M', 'stock' => 5,
        'image' => 'photo-1515372039744-b8f02a3ae446', 'active' => 1,
    ];
    $catalogue = new CatalogAdminController;
    $catalogue->product(req($productData, '/admin/products'));
    $product = DB::table('products')->where('name', $productData['name'])->firstOrFail();
    check((int) $product->brand_id === (int) $brand->id && $product->brand === $brand->name, 'Product dropdown selection stores its managed brand');
    $html = (new AdminController)->index(req([], '/admin', 'GET'))->render();
    check(str_contains($html, 'data-admin-list="brands"') && str_contains($html, 'name="brand_id"'), 'Admin renders brand table and product dropdown');
    $renamed = array_merge($data, ['id' => $brand->id, 'name' => 'Renamed QA ' . $suffix]);
    $controller->save(req($renamed));
    check(DB::table('products')->where('id', $product->id)->value('brand') === $renamed['name'], 'Brand rename updates linked product labels');
    req([], '/shop', 'GET');
    check((new MarketplaceController)->listing(request())->getData()['brands']->contains($renamed['name']), 'Active brand with products appears in the store filter');
    $controller->save(req(array_merge($renamed, ['active' => 0])));
    $productData['id'] = $product->id;
    $catalogue->product(req($productData, '/admin/products'));
    check((int) DB::table('products')->where('id', $product->id)->value('brand_id') === (int) $brand->id, 'Existing inactive brand assignment can be preserved');
    $newProduct = $productData; unset($newProduct['id']); $newProduct['name'] .= ' new';
    rejects(fn () => $catalogue->product(req($newProduct, '/admin/products')), 'Inactive brand cannot be assigned to a new product');
    req([], '/shop', 'GET');
    check(!(new MarketplaceController)->listing(request())->getData()['brands']->contains($renamed['name']), 'Inactive brand is excluded from store filter choices');
    $controller->save(req(array_merge($renamed, ['active' => 1])));
    Auth::login($seller);
    $sellerProduct = $newProduct;
    $sellerProduct['name'] = 'Seller Brand Product ' . $suffix;
    (new CatalogAdminController)->asSeller()->product(req($sellerProduct, '/seller/products'));
    $saved = DB::table('products')->where('name', $sellerProduct['name'])->firstOrFail();
    check((int) $saved->brand_id === (int) $brand->id && (int) $saved->seller_id === (int) $sellerId && $saved->seller_status === 'pending', 'Seller selects a managed brand while preserving moderation');
    $sellerHtml = (new App\Http\Controllers\SellerController)->index()->render();
    check(str_contains($sellerHtml, 'name="brand_id"') && !str_contains($sellerHtml, 'action="' . url('/admin/brands') . '"'), 'Seller has brand dropdown without master-brand controls');
    $invalid = $sellerProduct; $invalid['brand_id'] = 999999999;
    rejects(fn () => (new CatalogAdminController)->asSeller()->product(req($invalid, '/seller/products')), 'Unknown brand ID is rejected');
    Auth::login($admin);
    $productData['brand_id'] = null;
    $catalogue->product(req($productData, '/admin/products'));
    $unbranded = DB::table('products')->find($product->id);
    check($unbranded->brand_id === null && $unbranded->brand === null, 'Brand selection can be cleared to unbranded');
    check(!DB::table('products')->whereNotNull('brand')->where('brand', '!=', '')->whereNull('brand_id')->exists(), 'Existing nonempty product brands retain a managed mapping');
    echo "Brand checks passed. Fixtures rolled back; no emails sent.\n";
} finally {
    Auth::logout();
    DB::rollBack();
    foreach ($files as $file) if (is_file($file)) unlink($file);
}
