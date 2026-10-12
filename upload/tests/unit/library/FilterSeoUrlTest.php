<?php

use PHPUnit\Framework\TestCase;

require_once DIR_SYSTEM . 'library/filterseourl.php';

class FilterSeoUrlTest extends TestCase
{
    public function testNonAsciiSegmentsDoNotReachTheDatabase()
    {
        $db = $this->getMockBuilder(DB::class)->disableOriginalConstructor()
            ->onlyMethods(array('query', 'escape'))->getMock();
        $db->expects($this->never())->method('query');
        $db->expects($this->never())->method('escape');
        $repo = $this->repository($db);

        foreach (array("victor-a\xC3\xB1\xC2\x81026-\xC3\xB1\xC2\x81", 'цвет-синий', 'café', "emoji-\xF0\x9F\x98\x80", "invalid-\xFF") as $slug) {
            $this->assertNull($repo->resolveSlug($slug, 0, 1));
        }
    }

    public function testAsciiSlugStillResolvesWithStoreAndLanguageScope()
    {
        $db = $this->getMockBuilder(DB::class)->disableOriginalConstructor()
            ->onlyMethods(array('query', 'escape'))->getMock();
        $db->expects($this->once())->method('escape')->with('colour-blue')->willReturn('colour-blue');
        $db->expects($this->once())->method('query')->with($this->callback(function ($sql) {
            return strpos($sql, "`store_id` = '2'") !== false
                && strpos($sql, "`language_id` = '3'") !== false
                && strpos($sql, "`slug` = 'colour-blue'") !== false;
        }))->willReturn((object) array('num_rows' => 1, 'row' => array(
            'type' => 'fa', 'type_id' => '70', 'value_id' => '0', 'value_text' => 'Синий',
        )));

        $this->assertSame(array('type' => 'fa', 'type_id' => 70, 'value_id' => 0, 'value_text' => 'Синий'),
            $this->repository($db)->resolveSlug('colour-blue', 2, 3));
    }

    public function testUnknownAsciiSlugReturnsNull()
    {
        $db = $this->getMockBuilder(DB::class)->disableOriginalConstructor()
            ->onlyMethods(array('query', 'escape'))->getMock();
        $db->method('escape')->willReturnArgument(0);
        $db->expects($this->once())->method('query')->willReturn((object) array('num_rows' => 0));

        $this->assertNull($this->repository($db)->resolveSlug('unknown', 0, 1));
    }

    private function repository($db)
    {
        $registry = new Registry();
        $registry->set('db', $db);
        $registry->set('config', new Config());
        return new FilterSeoUrl($registry);
    }
}
