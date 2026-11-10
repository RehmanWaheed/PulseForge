<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->each->not->toBeUsed();

arch('strict types are declared across application code')
    ->expect('App')
    ->toUseStrictTypes();

arch('models must extend Eloquent Model')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('actions must be invokable')
    ->expect('App\Actions')
    ->toBeInvokable();

arch('dtos must be readonly')
    ->expect('App\DTOs')
    ->toBeReadonly();
