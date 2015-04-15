<?php

namespace React\Tests\Dns\Model;

use React\Dns\Query\Query;
use React\Dns\Model\Message;

class MessageTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateRequestDesiresRecusion()
    {
        $query = new Query('igor.io', Message::TYPE_A, Message::CLASS_IN, 1345656451);
        $request = Message::createRequestForQuery($query);

        $this->assertTrue($request->header->isQuery());
        $this->assertSame(1, $request->header->get('rd'));
    }
}
