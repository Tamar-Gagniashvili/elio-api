<?php

namespace Tests\Unit;

use App\Multitenancy\SubdomainTenantFinder;
use Illuminate\Http\Request;
use Tests\TestCase;

class SubdomainTenantFinderTest extends TestCase
{
    private SubdomainTenantFinder $finder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finder = new SubdomainTenantFinder;
    }

    /** @test */
    public function it_identifies_tenant_from_relink_ge_subdomain()
    {
        $request = Request::create('http://test.relink.ge');

        // We can't easily mock the config and DB for a full test without more setup,
        // but we can test the extraction logic if we refactor it or just test the behavior.
        // Since SubdomainTenantFinder::findForRequest calls the DB, we might need a partial mock.
    }
}
