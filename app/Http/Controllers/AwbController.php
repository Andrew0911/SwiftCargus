<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRequest;
use App\Http\Requests\EstimateCostRequest;
use App\Http\Requests\GenerateAwbRequest;
use App\Http\Requests\GetAdminAwbDetailsRequest;
use App\Http\Requests\GetAvailableStatusesForUpdateAwbRequest;
use App\Http\Requests\PrintAwbRequest;
use App\Http\Requests\UpdateAwbStatusRequest;
use App\Models\Address;
use App\Models\Awb;
use App\Models\Option;
use App\Models\Recipient;
use App\Models\Sender;
use App\Models\Status;
use App\Repositories\ClientRepository;
use App\Services\AwbService;
use App\Services\OptionsService;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class AwbController extends Controller
{
    public function generateAWB(GenerateAwbRequest $request) : Response
    {
        $userId = auth()->user()->id;
        $clientId = ClientRepository::getClientId($userId);
        $awbNumber = AwbService::getAwbNumber($userId, $clientId);
        $awbDate = Carbon::now()->addHours(3)->toDateTimeString();
        $awbValue = AwbService::calculateAwbValue(
            $request->serviceId,
            $request->options,
            $request->length,
            $request->width,
            $request->height,
            $request->weight,
            $request->packages
        );

        $senderAddress = AddressController::findAddressByData(
            $request->senderCountyId,
            $request->senderLocalityId,
            $request->senderStreet,
            $request->senderNr,
            $request->senderZipCode
        );

        if($senderAddress){
            $senderAddressId = $senderAddress->AddressId;
        } else {
        $senderAddressId = Address::create([
            'CountyId' => $request->senderCountyId,
            'LocalityId' => $request->senderLocalityId,
            'Street' => $request->senderStreet,
            'Nr' => $request->senderNr,
            'ZipCode' => $request->senderZipCode,
        ])->AddressId;
        }

        $senderId = Sender::create([
            'AddressId' => $senderAddressId,
            'Name' => $request->senderName,
            'Contact_person' => $request->senderContactPerson,
            'Email' => $request->senderEmail,
            'Phone' =>  $request->senderPhone
        ])->SenderId;

        $recipientAddress = AddressController::findAddressByData(
            $request->recipientCountyId,
            $request->recipientLocalityId,
            $request->recipientStreet,
            $request->recipientNr,
            $request->recipientZipCode
        );

        if($recipientAddress){
            $recipientAddressId = $recipientAddress->AddressId;
        } else {
        $recipientAddressId = Address::create([
            'CountyId' => $request->recipientCountyId,
            'LocalityId' => $request->recipientLocalityId,
            'Street' => $request->recipientStreet,
            'Nr' => $request->recipientNr,
            'ZipCode' => $request->recipientZipCode,
        ])->AddressId;
        }

        $recipientId = Recipient::create([
            'AddressId' => $recipientAddressId,
            'Name' => $request->recipientName,
            'Contact_person' => $request->recipientContactPerson,
            'Email' => $request->recipientEmail,
            'Phone' =>  $request->recipientPhone
        ])->RecipientId;

        Awb::create([
            'Awb' => $awbNumber,
            'ClientId' => $clientId,
            'SenderId' => $senderId,
            'RecipientId' => $recipientId,
            'Value' => $awbValue,
            'Date' => $awbDate,
            'ServiceId' => $request->serviceId,
            'Options' => implode("", $request->options),
            'PackageNo' => $request->packages,
            'Length' => $request->length,
            'Width' => $request->width,
            'Height' => $request->height,
            'Weight' => $request->weight,
            'StatusId' => 1
        ]);

        return response(['awbNumber' => $awbNumber]);
    }

    public function getClientAwbs(AdminRequest $request) : Response
    {
        $userId = auth()->user()->id;
        $clientId = ClientRepository::getClientId($userId);
        $awbs = $request->isAdmin === 'true' ? Awb::orderByDesc('Date')->get() :
                                               Awb::where('ClientId', $clientId)->orderByDesc('Date')->get();

        foreach($awbs as $awb)
        {
            $optionNamesArray = [];
            $senderName = Sender::where('SenderId', $awb->SenderId)->value('Name');
            $recipientName = Recipient::where('RecipientId', $awb->RecipientId)->value('Name');
            $serviceName = $awb->ServiceId == 1 ? 'Standard' : 'Heavy';
            foreach(str_split($awb->Options) as $option) {
                $optionNamesArray[] = Option::where('Code', $option)->value('Name');
            }
            $optionNames = implode(', ', $optionNamesArray) != '' ? implode(', ', $optionNamesArray) : 'None';
            $statusName = Status::where('StatusId', $awb->StatusId)->value('Name');

            $formattedAwbs[] = [
                'id' => $awb->Awb,
                'senderName' => $senderName,
                'recipientName' => $recipientName,
                'date' => $awb->Date,
                'service' => $serviceName,
                'options' => $optionNames,
                'value' => $awb->Value . ' RON',
                'status' => $statusName
            ];
        }
        
        return response($formattedAwbs ?? []);
    }

    public function getEachStatusAwbCount(AdminRequest $request) : Response
    {
        $userId = auth()->user()->id;
        $clientId = ClientRepository::getClientId($userId);
        $allStatuses = Status::select(['Name', 'StatusId'])->get();
        
        $countedStatuses = [];
        foreach($allStatuses as $status)
        {
            $numberOfAwbsWithThatStatus = $request->isAdmin === 'true' ? Awb::where('StatusId', $status->StatusId)->count() :
                                                                         Awb::where('ClientId', $clientId)->where('StatusId', $status->StatusId)->count();
            $countedStatuses[$status->Name] = $numberOfAwbsWithThatStatus;
        }
        return response($countedStatuses);
    }

    public function estimateAwbCost(EstimateCostRequest $request) : Response
    {
        $baseRate = $request->serviceId == 1 ? floatval(config('costs.standard-base-rate')) : floatval(config('costs.heavy-base-rate'));
        $weightRate = $request->serviceId == 1 ? floatval(config('costs.standard-weight-rate')) : floatval(config('costs.heavy-weight-rate')); 
        $volumeRate = floatval(config('costs.volume-rate'));

        $volume = $request->length * $request->width * $request->height;
        $volumeCost = (float) number_format($volume * $volumeRate, 2, '.', '');
        $weightCost = (float) number_format($request->weight * $weightRate, 2, '.', '');

        $additionalPackageCost = (float) number_format(($request->packages - 1) * floatval(config('costs.additional-package-cost')), 2, '.', '');
        $optionsCost = (float) number_format(OptionsService::getTotalOptionsCost($request->options ?? []), 2, '.', '');
        $vat = intval(config('costs.vat'));

        $costNoVat = (float) number_format($baseRate + $volumeCost + $weightCost + $additionalPackageCost + $optionsCost, 2, '.', '');
        $vatCost = (float) number_format($vat * $costNoVat / 100, 2, '.', '');

        return response([
            'ServiceCost' => $baseRate,
            'VolumeCost' => $volumeCost,
            'WeightCost' => $weightCost,
            'AdditionalPackagesCost' => $additionalPackageCost,
            'OptionsCost' => $optionsCost,
            'CostNoVat' => $costNoVat,
            'Vat' => $vatCost,
            'TotalCost' => (float) number_format($costNoVat + $vatCost, 2, '.', '')
        ]);
    }

    public function getPrintingDetails(PrintAwbRequest $request)
    {
        $userId = auth()->user()->id;
        $clientId = ClientRepository::getClientId($userId);
        
        $awb = $request->isAdmin === 'true' ? Awb::where('Awb', $request->awb)->first() :
                                   Awb::where('ClientId', $clientId)->where('Awb', $request->awb)->first();

        if(!$awb){
            return response(['message' => 'This AWB is not associated with your account']);
        }
        
        $sender = Sender::where('SenderId', $awb->SenderId)->first();
        $senderAddress = AddressController::findAddressById($sender->AddressId);
        $senderConty = AddressController::getCountyById($senderAddress->CountyId)->Name;
        $senderLocality = AddressController::getLocalityById($senderAddress->LocalityId)->Name;

        $recipient = Recipient::where('RecipientId', $awb->RecipientId)->first();
        $recipientAddress = AddressController::findAddressById($recipient->AddressId);
        $recipientConty = AddressController::getCountyById($recipientAddress->CountyId)->Name;
        $recipientLocality = AddressController::getLocalityById($recipientAddress->LocalityId)->Name;
        
        $serviceName = $awb->ServiceId == 1 ? 'Standard' : 'Heavy';

        $html = view('print.printAWB')->with([
            'awbNumber' => strval($awb->Awb),

            'senderName' => $sender->Name,
            'senderPhone' => $sender->Phone,
            'senderCounty' => $senderConty,
            'senderLocality' => $senderLocality,
            'senderStreet' => $senderAddress->Street,
            'senderNr' => $senderAddress->Nr,
            'senderZipCode' => $senderAddress->ZipCode,

            'recipientName' => $recipient->Name,
            'recipientPhone' => $recipient->Phone,
            'recipientCounty' => $recipientConty,
            'recipientLocality' => $recipientLocality,
            'recipientStreet' => $recipientAddress->Street,
            'recipientNr' => $recipientAddress->Nr,
            'recipientZipCode' => $recipientAddress->ZipCode,

            'date' => $awb->Date,
            'service' => $serviceName,
            'options' => $awb->Options == '' ? '-' : $awb->Options,
            'value' => $awb->Value . ' RON',
            'weight' => $awb->Weight,
            'length' => $awb->Length, 
            'width' => $awb->Width,
            'height' => $awb->Height,
            'packages' => $awb->PackageNo
        ]);

        return ['html' => $html->render()];
    }

    public function getTrackingDetails(PrintAwbRequest $request) : Response
    {
        $userId = auth()->user()->id;
        $clientId = ClientRepository::getClientId($userId);

        $awb = $request->isAdmin === 'true' ? Awb::where('Awb', $request->awb)->first() :
                                              Awb::where('ClientId', $clientId)->where('Awb', $request->awb)->first();

        if(!$awb){
            return response(['message' => 'This AWB is not associated with your account']);
        }
        
        $sender = Sender::where('SenderId', $awb->SenderId)->first();
        $senderAddress = AddressController::findAddressById($sender->AddressId);
        $senderCounty = AddressController::getCountyById($senderAddress->CountyId)->Name;
        $senderLocality = AddressController::getLocalityById($senderAddress->LocalityId)->Name;

        $recipient = Recipient::where('RecipientId', $awb->RecipientId)->first();
        $recipientAddress = AddressController::findAddressById($recipient->AddressId);
        $recipientCounty = AddressController::getCountyById($recipientAddress->CountyId)->Name;
        $recipientLocality = AddressController::getLocalityById($recipientAddress->LocalityId)->Name;

        [$senderLatitude, $senderLongitude] = AddressController::getAddressCoordinates($senderCounty, $senderLocality, $senderAddress->Street, $senderAddress->Nr, $senderAddress->ZipCode);
        [$recipientLatitude, $recipientLongitude] = AddressController::getAddressCoordinates($recipientCounty, $recipientLocality, $recipientAddress->Street, $recipientAddress->Nr, $recipientAddress->ZipCode);

        $formattedSenderAddress = AddressController::formatAddressDetails($senderCounty, $senderLocality, $senderAddress->Street, $senderAddress->Nr, $senderAddress->ZipCode);
        $formattedRecipientAddress =  AddressController::formatAddressDetails($recipientCounty, $recipientLocality, $recipientAddress->Street, $recipientAddress->Nr, $recipientAddress->ZipCode);

        $statusName = Status::where('StatusId', $awb->StatusId)->value('Name');

        return response([
            'senderName' => $sender->Name,
            'senderPhone' => $sender->Phone,
            'senderEmail' => $sender->Email,
            'senderAddress' => $formattedSenderAddress,
            'senderLatitude' => $senderLatitude,
            'senderLongitude' => $senderLongitude,

            'recipientName' => $recipient->Name,
            'recipientPhone' => $recipient->Phone,
            'recipientEmail' => $recipient->Email,
            'recipientAddress' => $formattedRecipientAddress,
            'recipientLatitude' => $recipientLatitude,
            'recipientLongitude' => $recipientLongitude,

            'status' => $statusName
        ]);
    }

    public function getCurrentMonthAwbs() : Response
    {
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $thisMonthAWBs = Awb::whereBetween('date', [$startOfMonth, $endOfMonth])
                    ->selectRaw('COUNT(*) as currentMonthCount, SUM(value) as totalValue')
                    ->first();

        $thisMonthDeliveredAWBs = Awb::whereBetween('date', [$startOfMonth, $endOfMonth])
                    ->where('StatusId', 4)
                    ->selectRaw('SUM(PackageNo) as totalPackages')
                    ->first();

        return response()->json([
            'awbs' => $thisMonthAWBs->currentMonthCount,
            'totalValue' => $thisMonthAWBs->totalValue,
            'packagesDelivered' => intval($thisMonthDeliveredAWBs->totalPackages)
        ]);
    }

    public function getCurrentYearAwbs() : Response
    {
        $fullMonthsArray = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $currentMonth = Carbon::now()->month;
        $monthsArray = array_slice($fullMonthsArray, 0, $currentMonth);

        $result = [];
        foreach ($monthsArray as $index => $month) {
            $result[$index] = [
                'month' => $month,
                'count' => 0
            ];
        }

        $startOfYear = Carbon::now()->startOfYear()->toDateString();
        $endOfYear = Carbon::now()->endOfYear()->toDateString();
        
        $awbs = AWB::selectRaw('MONTH(date) as month, COUNT(*) as currentMonthCount')
                    ->whereBetween('date', [$startOfYear, $endOfYear])
                    ->groupBy(DB::raw('MONTH(date)'))
                    ->get();

        foreach($awbs as $awb){
            $result[$awb->month - 1]['count'] = $awb->currentMonthCount;
        }
        
        return response()->json($result);
    }

    public function getAdminAwbDetails(GetAdminAwbDetailsRequest $request) : Response
    {
        $status = AWB::join('statuses', 'awb.StatusId', '=', 'statuses.StatusId')
                        ->where('awb.Awb', $request->awb)
                        ->select(['awb.StatusId', 'statuses.Name'])
                        ->first();

        return response()->json([
            'statusId' => $status->StatusId,
            'statusName' => $status->Name,
        ]);
    }

    public function updateAwbStatus(UpdateAwbStatusRequest $request) : Response
    {
        AWB::where('awb', $request->awb)->update(['StatusId' => $request->statusId]);

        return response()->json([
            'message' => 'success'
        ]);
    }

    public function getAvailableStatusesForUpdateAwb(GetAvailableStatusesForUpdateAwbRequest $request) : Response
    {
        $availableStatuses = Status::where('StatusId', '>' , $request->statusId)->get();
        return response()->json($availableStatuses);
    }
}
