<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\LocalityRequest;
use App\Models\Address;
use App\Models\County;
use App\Models\Locality;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddressController extends Controller
{
    const GEOCODER_URL = 'https://nominatim.openstreetmap.org/search?format=json&countrycodes=ro&limit=1&addressdetails=1&country=%s&county=%s&city=%s&street=%s';
    public function counties(Request $request) : Response
    {
        return response(County::all()->toArray() ?? []);
    }

    public function localitiesByCounty(LocalityRequest $request) : Response
    {
        return response(Locality::where("CountyId", $request->countyId)->select(["LocalityId", "Name"])->get() ?? []);
    }

    public static function findAddressByData(int $countyId, int $localityId, string $street, string $nr, string $zipCode) : ?Address
    {
        return Address::where('CountyId', $countyId)
                        ->where('LocalityId', $localityId)
                        ->where('Street', $street)
                        ->where('Nr', $nr)
                        ->where('ZipCode', $zipCode)
                        ->first();
    }

    public static function findAddressById(int $addressId) : ?Address
    {
        return Address::where("addressId", $addressId)->first();
    }

    public static function getLocalityById(int $localityId) : ?Locality
    {
        return Locality::where('LocalityId', $localityId)->first();
    }

    public static function getCountyById(int $countyId) : ?County
    {
        return County::where('CountyId', $countyId)->first();
    }
    public static function getAddressCoordinates(string $county, string $locality, string $street, string $number, string $zipCode) : array
    {
        $addressDetails = [
            'country' => 'Romania',
            'county' => $county,
            'locality' => $locality,
            'street' => $street . '/' . $number,
            'zipCode' => $zipCode
        ];
        
        $ch = curl_init();
        $url = sprintf(self::GEOCODER_URL, urlencode($addressDetails['country']), urlencode($addressDetails['county']), urlencode($addressDetails['locality']), urlencode($addressDetails['street']));

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1'
        ]);

        $geocodeCenter = curl_exec($ch);
        curl_close($ch);

        $geocoderResponse = json_decode($geocodeCenter);
        $latitude = 0;
        $longitude = 0;

        if (!empty($geocoderResponse)) {
            $geocoderResponse = $geocoderResponse[0];
            $latitude = (float)$geocoderResponse?->lat;
            $longitude = (float)$geocoderResponse?->lon;
        } 

        return [$latitude, $longitude];
    }

    public static function formatAddressDetails(string $county, string $locality, string $street, string $number, string $zipCode) : string
    {
        return 'Str. ' . $street . ', nr. ' . $number . ', Zip Code ' . $zipCode . ', ' . $county . ', ' . $locality;
    }
}
