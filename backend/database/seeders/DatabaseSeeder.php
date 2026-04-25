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
    private string $placeholderPng;

    public function run(): void
    {
        $this->placeholderPng = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );

        $users = $this->seedUsers();
        $posts = $this->seedPosts($users);
        $this->seedFollows($users);
        $this->seedLikes($users, $posts);
        $this->seedComments($users, $posts);

        $this->command->info('Seeder concluído.');
        $this->command->table(
            ['Usuário', 'Email', 'Senha'],
            collect($users)->map(fn ($u) => [$u->username, $u->email, 'password'])->toArray()
        );
    }

    private function seedUsers(): array
    {
        $data = [
            ['name' => 'Alice',   'username' => 'alice',   'email' => 'alice@example.com',   'bio' => 'Fotógrafa e viajante 📷'],
            ['name' => 'Bob',     'username' => 'bob',     'email' => 'bob@example.com',     'bio' => 'Desenvolvedor e gamer 🎮'],
            ['name' => 'Carol',   'username' => 'carol',   'email' => 'carol@example.com',   'bio' => 'Designer de UI/UX ✨'],
            ['name' => 'Diego',   'username' => 'diego',   'email' => 'diego@example.com',   'bio' => 'Músico e produtor 🎵'],
            ['name' => 'Elena',   'username' => 'elena',   'email' => 'elena@example.com',   'bio' => 'Escritora e poetisa 📚'],
            ['name' => 'Felipe',  'username' => 'felipe',  'email' => 'felipe@example.com',  'bio' => 'Chef amador e foodie 🍕'],
        ];

        return array_map(function (array $row): User {
            return User::firstOrCreate(
                ['email' => $row['email']],
                array_merge($row, ['password' => Hash::make('password')])
            );
        }, $data);
    }

    private function seedPosts(array $users): array
    {
        $captions = [
            ['Dia lindo hoje! ☀️', 'Melhor café da cidade ☕', 'Saudades do mar 🌊'],
            ['Setup novo 🖥️', 'Finalmente terminei o projeto! 🚀', 'Bug encontrado... e resolvido 💪'],
            ['Novo projeto em andamento 🎨', 'Paleta de cores favorita 🎨', 'Inspiração do dia ✨'],
            ['No estúdio hoje 🎹', 'Nova música chegando em breve 🎵', 'Jam session incrível!'],
            ['Capítulo novo publicado 📖', 'Inspiração veio de madrugada 🌙', 'Leitura da semana 📚'],
            ['Receita nova testada! 🍝', 'Brunch de domingo ☕🥞', 'Mercado municipal 🛒'],
        ];

        $posts = [];
        foreach ($users as $i => $user) {
            foreach ($captions[$i] as $caption) {
                $posts[] = $this->seedPost($user, $caption);
            }
        }

        return $posts;
    }

    private function seedFollows(array $users): void
    {
        [$alice, $bob, $carol, $diego, $elena, $felipe] = $users;

        // Alice follows everyone
        $alice->following()->syncWithoutDetaching([$bob->id, $carol->id, $diego->id, $elena->id]);

        // Bob follows alice and carol
        $bob->following()->syncWithoutDetaching([$alice->id, $carol->id, $felipe->id]);

        // Carol follows alice, bob, elena
        $carol->following()->syncWithoutDetaching([$alice->id, $bob->id, $elena->id]);

        // Diego follows alice and felipe
        $diego->following()->syncWithoutDetaching([$alice->id, $felipe->id]);

        // Elena follows carol and alice
        $elena->following()->syncWithoutDetaching([$alice->id, $carol->id, $diego->id]);

        // Felipe follows diego and bob
        $felipe->following()->syncWithoutDetaching([$bob->id, $diego->id]);
    }

    private function seedLikes(array $users, array $posts): void
    {
        $userIds = array_map(fn ($u) => $u->id, $users);

        foreach ($posts as $post) {
            // Each post gets likes from a random subset of other users
            $likerIds = array_filter($userIds, fn ($id) => $id !== $post->user_id && rand(0, 1));
            foreach ($likerIds as $likerId) {
                Like::firstOrCreate(['user_id' => $likerId, 'post_id' => $post->id]);
            }
        }
    }

    private function seedComments(array $users, array $posts): void
    {
        $commentPool = [
            'Incrível! 😍',
            'Que lindo! 🔥',
            'Adorei isso! ❤️',
            'Parabéns! 👏',
            'Que vibe boa! ✨',
            'Perfeito demais! 💯',
            'Me conta mais! 🙌',
            'Simplesmente incrível 🤩',
        ];

        foreach ($posts as $post) {
            $commenters = array_filter($users, fn ($u) => $u->id !== $post->user_id);
            $commenters = array_slice($commenters, 0, rand(1, 3));

            foreach ($commenters as $commenter) {
                $body = $commentPool[array_rand($commentPool)];
                Comment::firstOrCreate(
                    ['user_id' => $commenter->id, 'post_id' => $post->id, 'body' => $body]
                );
            }
        }
    }

    private function seedPost(User $user, string $caption): Post
    {
        $filename = Str::uuid() . '.png';
        Storage::disk('public')->put('posts/' . $filename, $this->placeholderPng);

        return Post::firstOrCreate(
            ['user_id' => $user->id, 'caption' => $caption],
            ['image_path' => 'posts/' . $filename]
        );
    }
}
