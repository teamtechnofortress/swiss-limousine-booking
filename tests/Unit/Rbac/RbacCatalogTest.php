<?php

namespace Tests\Unit\Rbac;

use App\Exceptions\RbacException;
use App\Services\Rbac\RbacCatalog;
use Tests\TestCase;

final class RbacCatalogTest extends TestCase
{
    public function test_shipped_configuration_is_valid(): void
    {
        $this->expectNotToPerformAssertions();

        app(RbacCatalog::class)->assertValid();
    }

    public function test_super_admin_receives_every_permission(): void
    {
        $catalog = app(RbacCatalog::class);

        $this->assertEqualsCanonicalizing(
            $catalog->permissionNames(),
            $catalog->permissionsFor('super_admin'),
        );
    }

    public function test_a_role_referencing_an_unknown_permission_fails_validation(): void
    {
        config(['rbac.roles.broken' => ['label' => 'Broken', 'permissions' => ['does.not.exist']]]);

        $this->expectException(RbacException::class);

        app(RbacCatalog::class)->assertValid();
    }
}
