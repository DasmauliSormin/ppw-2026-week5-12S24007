<?php

namespace App\Http\Controllers\Soap;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class LibrarySoapController extends Controller
{
    /**
     * Menyajikan Dokumen Kontrak WSDL (GET /soap/sipus.wsdl)
     */
    public function wsdl(): Response
    {
        $endpointUrl = url('/soap/sipus');
        $wsdlXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<definitions name="LibrarySoapService"
    targetNamespace="http://sipus.del.ac.id/soap/library"
    xmlns:tns="http://sipus.del.ac.id/soap/library"
    xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns="http://schemas.xmlsoap.org/wsdl/">

    <message name="CheckClearanceRequest">
        <part name="nim_peminjam" type="xsd:string"/>
    </message>
    <message name="CheckClearanceResponse">
        <part name="nim_peminjam" type="xsd:string"/>
        <part name="active_loans" type="xsd:int"/>
        <part name="is_cleared" type="xsd:boolean"/>
    </message>

    <portType name="LibraryPortType">
        <operation name="CheckClearanceStatus">
            <input message="tns:CheckClearanceRequest"/>
            <output message="tns:CheckClearanceResponse"/>
        </operation>
    </portType>

    <binding name="LibrarySoapBinding" type="tns:LibraryPortType">
        <soap:binding style="rpc" transport="http://schemas.xmlsoap.org/soap/http"/>
        <operation name="CheckClearanceStatus">
            <soap:operation soapAction="http://sipus.del.ac.id/soap/library#CheckClearanceStatus"/>
            <input><soap:body use="literal" namespace="http://sipus.del.ac.id/soap/library"/></input>
            <output><soap:body use="literal" namespace="http://sipus.del.ac.id/soap/library"/></output>
        </operation>
    </binding>

    <service name="LibrarySoapService">
        <port name="LibraryPort" binding="tns:LibrarySoapBinding">
            <soap:address location="{$endpointUrl}"/>
        </port>
    </service>
</definitions>
XML;

        return response($wsdlXml, 200)->header('Content-Type', 'text/xml; charset=utf-8');
    }

    /**
     * Memproses Pesan Masuk SOAP Envelope (POST /soap/sipus)
     */
    public function handle(Request $request): Response
    {
        $rawXml = trim($request->getContent());
        if (empty($rawXml)) {
            return $this->soapFault('soap:Client', 'Body XML SOAP tidak boleh kosong.');
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($rawXml);
        if ($xml === false) {
            return $this->soapFault('soap:Client', 'Format XML tidak valid (Malformed XML).');
        }

        // Ambil elemen nim_peminjam terlepas dari namespace
        $nimNodes = $xml->xpath('//*[local-name()="CheckClearanceRequest"]/*[local-name()="nim_peminjam"]');
        if (empty($nimNodes) || trim((string)$nimNodes[0]) === '') {
            return $this->soapFault('soap:Client', 'Elemen <nim_peminjam> wajib diisi.');
        }

        $nim = trim((string)$nimNodes[0]);

        // Cek tanggungan peminjaman aktif di tabel peminjamans
        $activeLoans = DB::table('peminjamans')
            ->where('nim_peminjam', $nim)
            ->where('status', 'dipinjam')
            ->count();

        $isCleared = ($activeLoans === 0) ? 'true' : 'false';
        $safeNim = htmlspecialchars($nim, ENT_XML1, 'UTF-8');

        $responseXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:m="http://sipus.del.ac.id/soap/library">
    <soap:Header>
        <m:ProviderInfo>SIPUS-Del Legacy Clearance Engine v1.0</m:ProviderInfo>
    </soap:Header>
    <soap:Body>
        <m:CheckClearanceResponse>
            <m:nim_peminjam>{$safeNim}</m:nim_peminjam>
            <m:active_loans>{$activeLoans}</m:active_loans>
            <m:is_cleared>{$isCleared}</m:is_cleared>
        </m:CheckClearanceResponse>
    </soap:Body>
</soap:Envelope>
XML;

        return response($responseXml, 200)->header('Content-Type', 'text/xml; charset=utf-8');
    }

    /**
     * Helper pesan error standar protokol SOAP (<soap:Fault>)
     */
    private function soapFault(string $faultCode, string $faultMessage): Response
    {
        $safeMsg = htmlspecialchars($faultMessage, ENT_XML1, 'UTF-8');
        $faultXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <soap:Fault>
            <faultcode>{$faultCode}</faultcode>
            <faultstring>{$safeMsg}</faultstring>
        </soap:Fault>
    </soap:Body>
</soap:Envelope>
XML;

        return response($faultXml, 500)->header('Content-Type', 'text/xml; charset=utf-8');
    }
}