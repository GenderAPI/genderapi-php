<?php

declare(strict_types=1);

namespace GenderApi\Tests;

use GenderApi\Client;
use GenderApi\Exception\ValidationException;
use GenderApi\Item;
use GenderApi\Tests\Support\Fixtures;
use GenderApi\Tests\Support\MockTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Invalid input must be rejected before any network request.
 */
final class ValidationTest extends TestCase
{
    /** @return iterable<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidSingle(): iterable
    {
        yield 'bad type' => [['type' => 'phone', 'value' => 'x'], '/type'];
        yield 'empty value' => [['type' => 'name', 'value' => ''], '/value'];
        yield 'whitespace value' => [['type' => 'name', 'value' => " \t "], '/value'];
        yield 'too long value' => [['type' => 'name', 'value' => str_repeat('a', 255)], '/value'];
        yield 'too long multibyte value' => [['type' => 'name', 'value' => str_repeat('ş', 255)], '/value'];
        yield 'newline in value' => [['type' => 'name', 'value' => "Onur\nX"], '/value'];
        yield 'NUL in value' => [['type' => 'name', 'value' => "On\0ur"], '/value'];
        yield 'DEL in value' => [['type' => 'name', 'value' => "On\x7Fur"], '/value'];
        yield 'invalid UTF-8' => [['type' => 'name', 'value' => "On\xC3ur"], '/value'];
        yield 'lowercase country' => [['type' => 'name', 'value' => 'Onur', 'country' => 'tr'], '/country'];
        yield 'three-letter country' => [['type' => 'name', 'value' => 'Onur', 'country' => 'TUR'], '/country'];
        yield 'empty country' => [['type' => 'name', 'value' => 'Onur', 'country' => ''], '/country'];
        yield 'bad ai_mode' => [['type' => 'name', 'value' => 'Onur', 'aiMode' => 'auto'], '/options/ai_mode'];
        yield 'force + off' => [['type' => 'name', 'value' => 'Onur', 'aiMode' => 'off', 'force' => true], '/options/ai_mode'];
        yield 'force + always' => [['type' => 'username', 'value' => 'x', 'aiMode' => 'always', 'force' => true], '/options/ai_mode'];
        yield 'empty id' => [['type' => 'name', 'value' => 'Onur', 'id' => ''], '/id'];
        yield 'too long id' => [['type' => 'name', 'value' => 'Onur', 'id' => str_repeat('i', 65)], '/id'];
    }

    /** @param array<string, mixed> $a */
    #[DataProvider('invalidSingle')]
    public function testInvalidSingleInputMakesNoRequest(array $a, string $pointer): void
    {
        $transport = new MockTransport();
        $client = new Client('0123456789abcdef01234567', transport: $transport);
        try {
            $client->gender($a['type'], $a['value'], $a['country'] ?? null, $a['aiMode'] ?? null, $a['force'] ?? false, $a['id'] ?? null);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame($pointer, $e->pointer);
        }
        self::assertSame([], $transport->requests);
    }

    public function testBoundaryValuesAreAccepted(): void
    {
        $item = Item::of('name', str_repeat('ş', 254), 'TR', 'fallback', true, str_repeat('i', 64));
        self::assertSame(254, preg_match_all('/./su', $item->toArray()['value']));
        self::assertSame(['ai_mode' => 'fallback'], $item->toArray()['options']);
        self::assertTrue($item->toArray()['forceToGenderize']);
        self::assertSame('{"type":"name","value":"Onur"}', json_encode(Item::name('Onur')));
    }

    /** @return iterable<string, array{0: list<mixed>, 1: string}> */
    public static function invalidBatches(): iterable
    {
        yield 'empty' => [[], '/items'];
        yield 'too many' => [array_fill(0, 51, ['type' => 'name', 'value' => 'a']), '/items'];
        yield 'duplicate ids' => [[['type' => 'name', 'value' => 'a', 'id' => 'x'], ['type' => 'name', 'value' => 'b', 'id' => 'x']], '/items/1/id'];
        yield 'renamed wire field' => [[['type' => 'name', 'name' => 'Onur']], '/items/0/name'];
        yield 'camelCase aiMode field' => [[['type' => 'name', 'value' => 'a', 'aiMode' => 'off']], '/items/0/aiMode'];
        yield 'unknown option' => [[['type' => 'name', 'value' => 'a', 'options' => ['ai_mode' => 'off', 'x' => 1]]], '/items/0/options/x'];
        yield 'options not array' => [[['type' => 'name', 'value' => 'a', 'options' => 'off']], '/items/0/options'];
        yield 'string force' => [[['type' => 'name', 'value' => 'a', 'forceToGenderize' => 'true']], '/items/0/forceToGenderize'];
        yield 'integer id' => [[['type' => 'name', 'value' => 'a', 'id' => 7]], '/items/0/id'];
        yield 'missing value' => [[['type' => 'email']], '/items/0/value'];
        yield 'force + off in batch' => [[['type' => 'name', 'value' => 'a', 'forceToGenderize' => true, 'options' => ['ai_mode' => 'off']]], '/items/0/options/ai_mode'];
        yield 'bad item type' => [['Onur'], '/items/0'];
        yield 'bad value in later item' => [[['type' => 'name', 'value' => 'a'], ['type' => 'name', 'value' => "b\r"]], '/items/1/value'];
        yield 'not a list' => [['a' => ['type' => 'name', 'value' => 'a']], '/items'];
    }

    /** @param list<mixed> $items */
    #[DataProvider('invalidBatches')]
    public function testInvalidBatchMakesNoRequest(array $items, string $pointer): void
    {
        $transport = new MockTransport();
        try {
            (new Client('0123456789abcdef01234567', transport: $transport))->genderBatch($items);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame($pointer, $e->pointer);
        }
        self::assertSame([], $transport->requests);
    }

    public function testFiftyItemsAreAccepted(): void
    {
        $rows = [];
        for ($i = 0; $i < 50; $i++) {
            $rows[] = ['index' => $i, 'charged_credits' => 1, 'data' => Fixtures::get('POST /api/v2/gender 200 dataset')['data']];
        }
        $transport = new MockTransport(MockTransport::json(200, ['data' => $rows, 'meta' => ['request_id' => 'r']]));
        $items = array_map(static fn (int $i) => Item::name('n' . $i, id: 'id-' . $i), range(0, 49));
        $result = (new Client('0123456789abcdef01234567', transport: $transport))->genderBatch($items);
        self::assertCount(50, $result);
        self::assertCount(50, $transport->lastJsonBody()['items']);
    }

    /** @return iterable<string, array{0: string, 1: ?string, 2: string}> */
    public static function invalidPhones(): iterable
    {
        yield 'too short' => ['12', 'TR', '/number'];
        yield 'too long' => [str_repeat('1', 33), 'TR', '/number'];
        yield 'letters' => ['555-CALL-NOW', 'US', '/number'];
        yield 'plus in middle' => ['90+555', 'TR', '/number'];
        yield 'national without country' => ['0555 000 00 00', null, '/country'];
        yield 'bad country' => ['0555 000 00 00', 'tr', '/country'];
    }

    #[DataProvider('invalidPhones')]
    public function testInvalidPhoneMakesNoRequest(string $number, ?string $country, string $pointer): void
    {
        $transport = new MockTransport();
        try {
            (new Client(null, transport: $transport))->validatePhone($number, $country);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame($pointer, $e->pointer);
        }
        self::assertSame([], $transport->requests);
    }

    public function testInternationalPhoneNeedsNoCountry(): void
    {
        $transport = new MockTransport(MockTransport::json(200, Fixtures::get('POST /api/v2/phone/validate 200 phone')));
        (new Client(null, transport: $transport))->validatePhone('+90 (555) 000-0000');
        self::assertSame(['number' => '+90 (555) 000-0000'], $transport->lastJsonBody());
    }
}
