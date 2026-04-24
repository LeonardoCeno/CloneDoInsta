<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $alice = User::firstOrCreate(
            ['email' => 'alice@example.com'],
            [
                'name'     => 'Alice',
                'username' => 'alice',
                'password' => Hash::make('password'),
                'bio'      => 'Fotógrafa e viajante 📷',
            ]
        );

        $bob = User::firstOrCreate(
            ['email' => 'bob@example.com'],
            [
                'name'     => 'Bob',
                'username' => 'bob',
                'password' => Hash::make('password'),
                'bio'      => 'Desenvolvedor e gamer 🎮',
            ]
        );

        $carol = User::firstOrCreate(
            ['email' => 'carol@example.com'],
            [
                'name'     => 'Carol',
                'username' => 'carol',
                'password' => Hash::make('password'),
                'bio'      => 'Designer de UI/UX ✨',
            ]
        );

        // Mutual follows
        $alice->following()->syncWithoutDetaching([$bob->id, $carol->id]);
        $bob->following()->syncWithoutDetaching([$alice->id]);
        $carol->following()->syncWithoutDetaching([$alice->id, $bob->id]);

        $alicePost  = $this->seedPost($alice,  'Dia lindo hoje! ☀️');
        $bobPost    = $this->seedPost($bob,    'Setup novo 🖥️');
        $carolPost  = $this->seedPost($carol,  'Novo projeto em andamento 🎨');

        // Cross-likes
        Like::firstOrCreate(['user_id' => $bob->id,   'post_id' => $alicePost->id]);
        Like::firstOrCreate(['user_id' => $carol->id,  'post_id' => $alicePost->id]);
        Like::firstOrCreate(['user_id' => $alice->id,  'post_id' => $bobPost->id]);
        Like::firstOrCreate(['user_id' => $carol->id,  'post_id' => $bobPost->id]);
        Like::firstOrCreate(['user_id' => $alice->id,  'post_id' => $carolPost->id]);

        // Comments
        Comment::firstOrCreate(
            ['user_id' => $bob->id, 'post_id' => $alicePost->id, 'body' => 'Que foto linda! 😍'],
        );
        Comment::firstOrCreate(
            ['user_id' => $carol->id, 'post_id' => $bobPost->id, 'body' => 'Setup incrível! 🔥'],
        );

        $this->command->info('Seeder concluído. Usuários: alice / bob / carol — senha: password');
    }

    private function seedPost(User $user, string $caption): Post
    {
        // 1×1 transparent PNG used as placeholder image for demo
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
        $filename = Str::uuid() . '.png';
        Storage::disk('public')->put('posts/' . $filename, $png);

        return Post::create([
            'user_id'    => $user->id,
            'image_path' => 'posts/' . $filename,
            'caption'    => $caption,
        ]);
    }
}
