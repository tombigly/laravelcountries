<?php

namespace PragmaRX\Countries\Tests\Service;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use PragmaRX\Coollection\Package\Coollection;
use PragmaRX\Countries\Package\Countries;
use PragmaRX\Countries\Update\Config as ServiceConfig;
use PragmaRX\Countries\Update\Helper;
use PragmaRX\Countries\Update\Updater;

class CountriesTest extends PHPUnitTestCase
{
    const COUNTRIES = 270;

    public function setUp(): void
    {
        ini_set('memory_limit', '2048M');

        Countries::getCache()->clear();
    }

    public function testUpdateCountries()
    {
        if (! function_exists('xdebug_get_code_coverage')) {
            ini_set('memory_limit', '4096M');

            $config = new ServiceConfig();

            $helper = new Helper($config);

            $updater = new Updater($config, $helper);

            $updater->update();
        }

        $this->assertTrue(! false);
    }

    public function testCountriesCanFilterOneCountry()
    {
        $brazil = Countries::where('name.common', 'Brazil')->first();

        $this->assertEquals($brazil['name']['common'], 'Brazil');
    }

    public function testCountriesHydrateCountryBordersFromOneCountry()
    {
        $brazil = countriesCollect(Countries::where('name.common', 'Brazil')->first())->hydrateBorders();

        $this->assertEquals(740, $brazil->get('borders')->where('name.common', 'Suriname')->first()['ccn3']);
    }

    public function testCanHydrateAllCountriesBorders()
    {
        Countries::all()->each(function ($country) {
            $hydrated = countriesCollect($country)->hydrate('borders');

            if ($hydrated->get('borders')->count()) {
                $this->assertNotEmpty(collect($hydrated->get('borders')->first())->get('name'));
            } else {
                $this->assertTrue($hydrated->get('borders')->first() === null);
            }
        });
    }

    public function testCanHydrateAllTimezones()
    {
        Countries::all()->each(function ($country) {
            $hydrated = countriesCollect($country)->hydrate('timezones');

            if ($hydrated->get('timezones')->count()) {
                $this->assertNotEmpty(($hydrated->get('timezones')->first())['abbreviations']);
            } else {
                $this->assertEquals(null, $hydrated->get('timezones')->first());
            }
        });
    }

    public function testCanGetASingleBorder()
    {
        $this->assertEquals(
            'Venezuela',
            Countries::where('name.common', 'Brazil')
                ->hydrate('borders')
                ->first()
                ->get('borders')
                ->reverse()
                ->first()['name']['common']
        );
    }

    public function testCountryDoesNotExist()
    {
        $this->assertTrue(
            Countries::where('name.common', 'not a country')->isEmpty()
        );
    }

    public function testStatesAreHydrated()
    {
        $this->assertEquals(27, countriesCollect(Countries::where('name.common', 'Brazil')->first())->hydrate('states')->get('states')->count());

        $this->assertEquals(51, countriesCollect(Countries::where('cca3', 'USA')->first())->hydrate('states')->get('states')->count());

        $this->assertEquals(
            'Northeast',
            countriesCollect(Countries::where('cca3', 'USA')->first())->hydrate('states')['states']['NY']['extra']['region']
        );
    }

    public function testCanGetAState()
    {
        $this->assertEquals(
            'Agrigento',
            countriesCollect(Countries::where('name.common', 'Italy')->first())->hydrate('states')->get('states')->sortBy(function ($state) {
                return $state['name'];
            })->first()['name']
        );
    }

    public function _testAllHydrations()
    {
        $elements = Countries::getConfig()->get('hydrate.elements')->map(function ($value) {
            return true;
        })->toArray();

        $hydrated = Countries::where('tld.0', '.nz')->hydrate($elements);

        $this->assertNotNull($hydrated->first()->borders);
        $this->assertNotNull($hydrated->first()->cities);
        $this->assertNotNull($hydrated->first()->currencies);
        $this->assertNotNull($hydrated->first()->flag->sprite);
        $this->assertNotNull($hydrated->first()->geometry);
        $this->assertNotNull($hydrated->first()->hydrateStates()->states);
        $this->assertNotNull($hydrated->first()->taxes);
        $this->assertNotNull($hydrated->first()->timezones);
        $this->assertNotNull($hydrated->first()->topology);
    }

    public function testWhereLanguage()
    {
        $this->assertGreaterThan(0, Countries::where('languages.pap', 'Papiamento')->count());
    }

    public function _testWhereCurrency()
    {
        $shortName = Countries::where('ISO4217', 'EUR')->count();

        $this->assertGreaterThan(0, $shortName);
    }

    public function testMagicCall()
    {
        $this->assertEquals(
            Countries::whereNameCommon('Brazil')->count(),
            Countries::where('name.common', 'Brazil')->count()
        );

        $this->assertEquals(
            Countries::whereISO639_3('por')->count(),
            Countries::where('ISO639_3', 'por')->count()
        );

        $this->assertEquals(
            Countries::whereLca3('por')->count(),
            Countries::where('lca3', 'por')->count()
        );
    }

    public function testMapping()
    {
        $this->assertGreaterThan(0, Countries::where('cca3', 'BRA')->count());
    }

    public function testCurrencies()
    {
        $this->assertEquals(Countries::currencies()->count(), 153);

        $this->assertTrue(
            in_array('CHF1000', countriesCollect(
                Countries::where('cca3', 'CHE')->first())->hydrate('currencies')
                ->get('currencies')
                ->get('CHF')
                ->get('banknotes')['frequent']
            )
        );
    }

    public function testTimezones()
    {
        $this->assertEquals(
            countriesCollect(Countries::where('cca3', 'FRA')->first())->hydrate('timezones')->get('timezones')->first()['zone_name'],
            'Europe/Paris'
        );

        $this->assertEquals(
            countriesCollect(Countries::where('name.common', 'United States')->first())->hydrate('timezones')->get('timezones')->first()['zone_name'],
            'America/Adak'
        );
    }

    public function testHydratorMethods()
    {
        $this->assertEquals(
            countriesCollect(Countries::where('cca3', 'FRA')->first())->hydrate('timezones')->get('timezones')->get('europe_paris')['zone_name'],
            'Europe/Paris'
        );

        $this->assertEquals(
            countriesCollect(Countries::where('cca3', 'JPN')->first())->hydrateTimezones()->get('timezones')->get('asia_tokyo')['zone_name'],
            'Asia/Tokyo'
        );
    }

    public function testOldIncorrectStates()
    {
        $c = countriesCollect(Countries::where('cca3', 'BRA')->first())->hydrate('states');

        $this->assertEquals('BR-RO', $c->get('states')->get('RO')['iso_3166_2']);
        $this->assertEquals('BR.RO', $c->get('states')->get('RO')['code_hasc']);
        $this->assertEquals('RO', $c->get('states')->get('RO')['postal']);

        $this->assertEquals(
            'Puglia',
            countriesCollect(Countries::where('cca3', 'ITA')->first())->hydrate('states')->get('states')['BA']['region']
        );

        $this->assertEquals(
            'Sicilia',
            countriesCollect(Countries::where('cca3', 'ITA')->first())->hydrate('states')->get('states')['TP']['region']
        );
    }

    public function testCanGetCurrency()
    {
        $this->assertEquals(
            'R$',
            countriesCollect(Countries::where('name.common', 'Brazil')->first())
                ->hydrate('currencies')->get('currencies')->get('BRL')
                ->get('units')
                ['major']
                ['symbol']
        );
    }

    public function testTranslation()
    {
        $this->assertEquals(
            'Repubblica federativa del Brasile',
            Countries::where('name.common', 'Brazil')->first()['translations']['ita']['official']
        );
    }

    public function testCitiesHydration()
    {
        $this->assertEquals(
            countriesCollect(Countries::where('cca3', 'FRA')->first())->hydrate('cities')->get('cities')->get('paris')['timezone'],
            'Europe/Paris'
        );
    }

    public function testNumberOfCurrencies()
    {
        $number = Countries::all()->hydrate('currencies')->pluck('currencies')->map(function ($value) {
            return $value->keys()->flatten()->toArray();
        })->flatten()->filter(function ($value) {
            return $value !== 'unknown';
        })->sort()->values()->unique()->count();

        return $this->assertEquals(171, $number); // current state 2022-02
    }

    public function testNumberOfBorders()
    {
        $number = Countries::all()->pluck('borders')->map(function ($value) {
            if (is_null($value)) {
                return [];
            }

            return collect($value)->keys()->flatten()->toArray();
        })->count();

        $this->assertEquals(self::COUNTRIES, $number); // current state 2022-02
    }

    public function testNumberOfLanguages()
    {
        $number = Countries::all()->pluck('languages')->map(function ($value) {
            if (is_null($value)) {
                return;
            }

            return collect($value)->keys()->flatten()->mapWithKeys(function ($value, $key) {
                return [$value => $value];
            })->toArray();
        })->flatten()->unique()->values()->reject(function ($value) {
            return is_null($value);
        })->count();

        $this->assertEquals(156, $number);
    }

    public function testFindCountryByCca2()
    {
        $this->assertEquals(
            'Puglia',
            countriesCollect(Countries::where('cca2', 'IT')->first())->hydrate('states')->get('states')->toArray()['BA']['region']
        );
    }

    public function testStatesFromNetherlands()
    {
        $neds = countriesCollect(Countries::where('name.common', 'Netherlands')
            ->first())
            ->hydrate('states')
            ->get('states')
            ->sortBy('name')
            ->pluck('name')
            ->count();

        $this->assertEquals(15, $neds);
    }

    public function testHydrateOneElementOnly()
    {
        $this->assertEquals(
            110,
            countriesCollect(Countries::where('cca2', 'IT')->first())->hydrate('states')->get('states')->count()
        );
    }

    public function testHydrateEurope()
    {
        $this->assertEquals(
            'Europe Union',
            Countries::where('cca3', 'EUR')->first()['name']['common']
        );
    }

    public function testLoadAllCurrencies()
    {
        $this->assertEquals(
            '€1',
            countriesCollect(Countries::where('cca2', 'IT')->first())->hydrate('currencies')->get('currencies')->get('EUR')->get('coins')['frequent'][0]
        );
    }

    public function testCanGetPropertyWithAnyCase()
    {
        $c = countriesCollect(Countries::where('cca2', 'IT')->first())->hydrate('currencies')->get('currencies');

        $this->assertEquals(
            '€1',
            collect($c->get('EUR')->get('coins') ['frequent'])->first()
        );

        $this->assertEquals(
            '50c',
            collect($c->get('EUR')->get('coins') ['frequent'])->last()
        );
    }

    public function testHydrateTaxes()
    {
        $this->assertEquals(
            'it_vat',
            countriesCollect(Countries::where('cca2', 'IT')->first())->hydrate('taxes')->get('taxes')->get('vat')['zone']
        );
    }

    public function stringForComparison($string)
    {
        return str_replace(
            ["\n", '\n', '\\', '/', ' '],
            ['',   '',   '',   '',  ''],
            $string
        );
    }

    public function testCanSortArrayByKeys()
    {
        $a = [
            'f' => 'e',
            'd' => [
                'c' => [
                    'b' => 1,
                ],
                'a' => 2,
            ],
        ];

        $b = [
            'd' => [
                'a' => 2,
                'c' => [
                    'b' => 1,
                ],
            ],
            'f' => 'e',
        ];

        array_sort_by_keys_recursive($a);

        $this->assertEquals($b, $a);
    }

    public function _testCanHydrateTimezonesTimes()
    {
        $this->assertEquals(
            countriesCollect(Countries::where('name.common', 'United States Virgin Islands')->first())
                ->hydrate('timezones_times')
                ->get('timezones')
                ->first()
                ->times
                ->time_start,
            '-2233035336'
        ); // current state 2022-02
    }

    public function testPluck()
    {
        $c = new Countries;

        $this->assertEquals(self::COUNTRIES, $c->all()->count()); // current state 2022-02

        $this->assertEquals(self::COUNTRIES, $c->all()->pluck('name.common')->count()); // current state 2022-02
    }
}
