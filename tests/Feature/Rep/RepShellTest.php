<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Distribution\Models\SalesRep;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 * The rep interface's own front door. Distinct from the back office's, but
 * on the same guard and the same users table — see
 * app/Http/Controllers/Rep/RepAuthController.php.
 */

it('shows the login page to a guest', function () {
    get('/rep/login')->assertOk()->assertSee('Sign in');
});

it('sends a guest at the shell to log in first', function () {
    get('/rep')->assertRedirect('/rep/login');
});

it('logs a rep in and lands on the shell', function () {
    $user = User::factory()->rep()->create(['password' => bcrypt('correct-password')]);
    SalesRep::factory()->create(['user_id' => $user->id]);

    post('/rep/login', ['email' => $user->email, 'password' => 'correct-password'])
        ->assertRedirect('/rep');

    get('/rep')->assertOk()->assertSee("Today's round", false);
});

it('refuses the wrong password', function () {
    $user = User::factory()->rep()->create(['password' => bcrypt('correct-password')]);
    SalesRep::factory()->create(['user_id' => $user->id]);

    post('/rep/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');
});

it('turns away a back-office user with no sales rep record', function () {
    actingAs(User::factory()->admin()->create());

    get('/rep')->assertForbidden();
});

it('logs a rep out back to the login page', function () {
    $user = User::factory()->rep()->create();
    SalesRep::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    post('/rep/logout')->assertRedirect('/rep/login');

    get('/rep')->assertRedirect('/rep/login');
});
