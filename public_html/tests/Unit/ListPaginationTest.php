<?php

/**
 * Unit tests for shared list pagination helpers.
 */

require_once __DIR__ . '/../../includes/list_pagination.php';

class ListPaginationTest extends PHPUnit\Framework\TestCase
{
    public function testDefaultOptions(): void
    {
        $this->assertSame(10, DEFAULT_PER_PAGE);
        $this->assertSame([10, 25, 50, 100], PER_PAGE_OPTIONS);
    }

    public function testListPaginationStateWithExplicitArgs(): void
    {
        $state = listPaginationState(45, 1, 10);
        $this->assertSame(10, $state['perPage']);
        $this->assertSame(1, $state['page']);
        $this->assertSame(5, $state['totalPages']);
        $this->assertSame(0, $state['offset']);
        $this->assertSame(1, $state['from']);
        $this->assertSame(10, $state['to']);

        $page2 = listPaginationState(45, 2, 10);
        $this->assertSame(10, $page2['offset']);
        $this->assertSame(11, $page2['from']);
        $this->assertSame(20, $page2['to']);
    }

    public function testListPaginationStateClampsPage(): void
    {
        $state = listPaginationState(12, 99, 10);
        $this->assertSame(2, $state['page']);
        $this->assertSame(2, $state['totalPages']);
    }
}
