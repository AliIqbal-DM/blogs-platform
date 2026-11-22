<?php

namespace Tests\Tenancy;

use App\Actions\Tenants\CreateTenant;
use App\Models\Post;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class CoverImageTest extends TenantTestCase
{
    private const ACME = 'http://acme.blogs.test';

    private const GLOBEX = 'http://globex.blogs.test';

    private Tenant $acme;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = app(CreateTenant::class)->handle(id: 'acme', name: 'Acme', domain: 'acme.blogs.test');
    }

    public function test_upload_is_stored_in_the_tenants_own_storage_folder(): void
    {
        $this->actingAsWriter($this->acme)
            ->post(self::ACME.'/dashboard/posts', $this->validPost([
                'cover' => UploadedFile::fake()->image('cover.png', 1600, 900),
            ]))
            ->assertSessionHasNoErrors();

        $path = $this->acme->run(fn () => Post::sole()->cover_image);

        $this->assertMatchesRegularExpression('#^covers/[A-Za-z0-9]{40}\.png$#', $path);
        $this->assertFileExists($this->tenantStoragePath('acme', "app/public/{$path}"));
        $this->assertFileDoesNotExist(base_path("storage/app/public/{$path}"));
    }

    public function test_two_tenants_uploading_the_same_file_name_get_separate_files(): void
    {
        $globex = app(CreateTenant::class)->handle(id: 'globex', name: 'Globex', domain: 'globex.blogs.test');

        $acmeImage = UploadedFile::fake()->image('cover.png', 800, 450);
        $globexImage = UploadedFile::fake()->image('cover.png', 1200, 675);

        $this->actingAsWriter($this->acme)
            ->post(self::ACME.'/dashboard/posts', $this->validPost(['cover' => $acmeImage]))
            ->assertSessionHasNoErrors();
        $this->actingAsWriter($globex)
            ->post(self::GLOBEX.'/dashboard/posts', $this->validPost(['cover' => $globexImage]))
            ->assertSessionHasNoErrors();

        $acmePath = $this->acme->run(fn () => Post::sole()->cover_image);
        $globexPath = $globex->run(fn () => Post::sole()->cover_image);

        $acmeFile = $this->tenantStoragePath('acme', "app/public/{$acmePath}");
        $globexFile = $this->tenantStoragePath('globex', "app/public/{$globexPath}");

        $this->assertNotSame($acmeFile, $globexFile);
        $this->assertSame(md5_file($acmeImage->getPathname()), md5_file($acmeFile));
        $this->assertSame(md5_file($globexImage->getPathname()), md5_file($globexFile));
        $this->assertFileDoesNotExist($this->tenantStoragePath('globex', "app/public/{$acmePath}"));
        $this->assertFileDoesNotExist($this->tenantStoragePath('acme', "app/public/{$globexPath}"));
    }

    public function test_cover_url_points_at_the_tenant_asset_route_and_serves_the_file(): void
    {
        $this->actingAsWriter($this->acme)
            ->post(self::ACME.'/dashboard/posts', $this->validPost([
                'status' => 'published',
                'cover' => UploadedFile::fake()->image('cover.png'),
            ]))
            ->assertSessionHasNoErrors();

        [$path, $slug] = $this->acme->run(fn () => [Post::sole()->cover_image, Post::sole()->slug]);
        $url = self::ACME."/tenancy/assets/{$path}";

        $this->freshRequestState();
        $this->get(self::ACME.'/')
            ->assertInertia(fn (Assert $page) => $page->where('posts.data.0.cover_url', $url));
        $this->get(self::ACME."/posts/{$slug}")
            ->assertInertia(fn (Assert $page) => $page->where('post.cover_url', $url));

        $this->freshRequestState();
        $response = $this->get($url)->assertOk();

        $this->assertSame(
            realpath($this->tenantStoragePath('acme', "app/public/{$path}")),
            $response->baseResponse->getFile()->getRealPath(),
        );
    }

    public function test_posts_without_a_cover_have_a_null_cover_url(): void
    {
        $this->acme->run(fn () => Post::factory()->published()->create());

        $this->freshRequestState();
        $this->get(self::ACME.'/')
            ->assertInertia(fn (Assert $page) => $page->where('posts.data.0.cover_url', null));
    }

    public function test_replacing_the_cover_deletes_the_old_file(): void
    {
        $id = $this->postWithCover();
        $oldFile = $this->coverFile($id);

        $this->actingAsWriter($this->acme)
            ->put(self::ACME."/dashboard/posts/{$id}", $this->validPost([
                'cover' => UploadedFile::fake()->image('new-cover.webp'),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($this->coverFile($id));
        $this->assertStringEndsWith('.webp', $this->coverFile($id));
    }

    public function test_remove_cover_deletes_the_file_and_clears_the_column(): void
    {
        $id = $this->postWithCover();
        $oldFile = $this->coverFile($id);

        $this->actingAsWriter($this->acme)
            ->put(self::ACME."/dashboard/posts/{$id}", $this->validPost(['remove_cover' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertFileDoesNotExist($oldFile);
        $this->assertNull($this->acme->run(fn () => Post::findOrFail($id)->cover_image));
    }

    public function test_updating_without_a_new_cover_keeps_the_current_one(): void
    {
        $id = $this->postWithCover();
        $file = $this->coverFile($id);

        $this->actingAsWriter($this->acme)
            ->put(self::ACME."/dashboard/posts/{$id}", $this->validPost(['title' => 'Renamed', 'remove_cover' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertFileExists($file);
        $this->assertSame($file, $this->coverFile($id));
    }

    public function test_deleting_a_post_deletes_its_cover_file(): void
    {
        $id = $this->postWithCover();
        $file = $this->coverFile($id);

        $this->actingAsWriter($this->acme)
            ->delete(self::ACME."/dashboard/posts/{$id}")
            ->assertRedirect(self::ACME.'/dashboard/posts');

        $this->assertFileDoesNotExist($file);
    }

    public function test_a_failed_save_does_not_leave_the_new_upload_behind(): void
    {
        // Fails after validation passed and the upload was stored.
        Post::saving(fn () => throw new RuntimeException('Simulated database failure.'));

        $this->actingAsWriter($this->acme)
            ->post(self::ACME.'/dashboard/posts', $this->validPost([
                'cover' => UploadedFile::fake()->image('cover.png'),
            ]))
            ->assertServerError();

        $this->assertDirectoryExists($this->tenantStoragePath('acme', 'app/public/covers'));
        $this->assertSame([], glob($this->tenantStoragePath('acme', 'app/public/covers/*')) ?: []);
        $this->assertSame(0, $this->acme->run(fn () => Post::count()));
    }

    /**
     * @return array<string, array{UploadedFile}>
     */
    public static function invalidCovers(): array
    {
        return [
            'svg' => [UploadedFile::fake()->createWithContent(
                'cover.svg',
                '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            )],
            'gif' => [UploadedFile::fake()->image('cover.gif')],
            'pdf' => [UploadedFile::fake()->create('cover.pdf', 10, 'application/pdf')],
            'text named .png' => [static::realUpload('cover.png', 'not really an image')],
            'larger than 4 MB' => [UploadedFile::fake()->image('cover.jpg')->size(4097)],
        ];
    }

    #[DataProvider('invalidCovers')]
    public function test_invalid_covers_are_rejected(UploadedFile $cover): void
    {
        $this->actingAsWriter($this->acme)
            ->post(self::ACME.'/dashboard/posts', $this->validPost(['cover' => $cover]))
            ->assertSessionHasErrors('cover');

        $this->assertSame(0, $this->acme->run(fn () => Post::count()));
        $this->assertDirectoryDoesNotExist($this->tenantStoragePath('acme', 'app/public/covers'));
    }

    public function test_a_cover_of_exactly_4_mb_is_accepted(): void
    {
        $this->actingAsWriter($this->acme)
            ->post(self::ACME.'/dashboard/posts', $this->validPost([
                'cover' => UploadedFile::fake()->image('cover.jpg')->size(4096),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($this->acme->run(fn () => Post::sole()->cover_image));
    }

    public function test_edit_page_and_index_include_the_cover_url(): void
    {
        $id = $this->postWithCover();
        $path = $this->acme->run(fn () => Post::findOrFail($id)->cover_image);
        $url = self::ACME."/tenancy/assets/{$path}";

        $writer = $this->actingAsWriter($this->acme);

        $writer->get(self::ACME."/dashboard/posts/{$id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('post.cover_url', $url));
        $writer->get(self::ACME.'/dashboard/posts')
            ->assertInertia(fn (Assert $page) => $page->where('posts.0.cover_url', $url));
    }

    /**
     * Fake uploads guess their MIME type from the name; a real UploadedFile sniffs the content.
     */
    private static function realUpload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cover');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function postWithCover(): int
    {
        $this->actingAsWriter($this->acme)
            ->post(self::ACME.'/dashboard/posts', $this->validPost([
                'cover' => UploadedFile::fake()->image('cover.png'),
            ]))
            ->assertSessionHasNoErrors();

        $id = $this->acme->run(fn () => Post::sole()->id);
        $this->assertFileExists($this->coverFile($id));

        return $id;
    }

    private function coverFile(int $postId): string
    {
        $path = $this->acme->run(fn () => Post::findOrFail($postId)->cover_image);

        return $this->tenantStoragePath('acme', "app/public/{$path}");
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPost(array $overrides = []): array
    {
        return [
            'title' => 'Hello World',
            'body' => 'Body text.',
            'status' => 'draft',
            'publish_at' => null,
            ...$overrides,
        ];
    }
}
