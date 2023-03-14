<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @Author Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Controller
 */
class WsdlController extends AbstractController
{
    private SerializerInterface $serializer;

    public function __construct(
        EntityManagerInterface $entityManager,
        ParameterBagInterface $params,
        SerializerInterface $serializer
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/wsdl")
     */
    public function wsdlAction()
    {
        $wsdl =
            '<?xml version="1.0" encoding="utf-8"?>
<wsdl:definitions xmlns:s="http://www.w3.org/2001/XMLSchema" xmlns:s2="http://www.w3.org/2005/05/xmlmime" xmlns:soap12="http://schemas.xmlsoap.org/wsdl/soap12/" xmlns:http="http://schemas.xmlsoap.org/wsdl/http/" xmlns:mime="http://schemas.xmlsoap.org/wsdl/mime/" xmlns:tns="http://www.centric.nl/Publieksdiensten/Conductor/1.0" xmlns:s0="urn:Centric/Publieksdiensten/Conductor/1.0" xmlns:s1="http://schemas.microsoft.com/BizTalk/2003/Any" xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/" xmlns:tm="http://microsoft.com/wsdl/mime/textMatching/" xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/" targetNamespace="http://www.centric.nl/Publieksdiensten/Conductor/1.0" xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/">
<wsdl:types>
<s:schema elementFormDefault="qualified" targetNamespace="urn:Centric/Publieksdiensten/Conductor/1.0">
<s:import namespace="http://schemas.microsoft.com/BizTalk/2003/Any" />
<s:import namespace="http://www.w3.org/2005/05/xmlmime" />
<s:element name="OntvangenIntakeNotificatie">
<s:complexType>
<s:sequence>
<s:element minOccurs="0" maxOccurs="1" ref="s1:Body" />
<s:element minOccurs="0" maxOccurs="1" name="Bijlagen">
<s:complexType>
<s:sequence>
<s:element minOccurs="0" maxOccurs="unbounded" name="Bijlage" type="s0:DocumentType" />
</s:sequence>
</s:complexType>
</s:element>
</s:sequence>
</s:complexType>
</s:element>
<s:complexType name="DocumentType">
<s:sequence>
<s:element minOccurs="0" maxOccurs="1" name="Naam" type="s:string" />
<s:element minOccurs="0" maxOccurs="1" name="Omschrijving" type="s:string" />
<s:element minOccurs="0" maxOccurs="1" name="Inhoud" type="s2:base64Binary" />
<s:element minOccurs="0" maxOccurs="1" name="Metadata" type="s0:ArrayOfDocumentTypeMarkering" />
</s:sequence>
<s:attribute name="id" type="s:string" />
</s:complexType>
<s:complexType name="ArrayOfDocumentTypeMarkering">
<s:sequence>
<s:element minOccurs="0" maxOccurs="unbounded" name="Markering">
<s:complexType>
<s:sequence>
<s:element minOccurs="0" maxOccurs="1" name="Naam" type="s:string" />
<s:element minOccurs="0" maxOccurs="1" name="Waarde" type="s:string" />
</s:sequence>
</s:complexType>
</s:element>
</s:sequence>
</s:complexType>
<s:element name="OntvangenIntakeAntwoord">
<s:complexType />
</s:element>
<s:element name="Stuurgegevens">
<s:complexType>
<s:sequence>
<s:element minOccurs="0" maxOccurs="1" name="Zaaktype" type="s:string" />
<s:element minOccurs="1" maxOccurs="1" name="Versie" nillable="true" type="s:decimal" />
<s:element minOccurs="1" maxOccurs="1" name="Berichttype">
<s:simpleType>
<s:restriction base="s:string">
<s:enumeration value="intake" />
<s:enumeration value="statusupdate" />
<s:enumeration value="zaakupdate" />
<s:enumeration value="bijlageupdate" />
<s:enumeration value="bijlagedelete" />
</s:restriction>
</s:simpleType>
</s:element>
<s:element minOccurs="0" maxOccurs="1" name="Zender" type="s:string" />
<s:element minOccurs="0" maxOccurs="1" name="Ontvanger" type="s:string" />
<s:element minOccurs="0" maxOccurs="1" name="Sleutel">
<s:complexType>
<s:simpleContent>
<s:extension base="s:string">
<s:attribute name="type">
<s:simpleType>
<s:restriction base="s:string">
<s:enumeration value="zender" />
<s:enumeration value="ontvanger" />
</s:restriction>
</s:simpleType>
</s:attribute>
</s:extension>
</s:simpleContent>
</s:complexType>
</s:element>
<s:element minOccurs="0" maxOccurs="1" name="Datum" type="s:dateTime" />
</s:sequence>
<s:anyAttribute />
</s:complexType>
</s:element>
</s:schema>
<s:schema elementFormDefault="qualified" targetNamespace="http://schemas.microsoft.com/BizTalk/2003/Any">
<s:element name="Body">
<s:complexType mixed="true">
<s:sequence>
<s:any />
</s:sequence>
</s:complexType>
</s:element>
</s:schema>
<s:schema elementFormDefault="qualified" targetNamespace="http://www.w3.org/2005/05/xmlmime">
<s:complexType name="base64Binary">
<s:simpleContent>
<s:extension base="s:base64Binary">
<s:attribute form="qualified" name="contentType" type="s:string" />
</s:extension>
</s:simpleContent>
</s:complexType>
</s:schema>
</wsdl:types>
<wsdl:message name="OntvangenIntakeSoapIn">
<wsdl:part name="parameters" element="s0:OntvangenIntakeNotificatie" />
</wsdl:message>
<wsdl:message name="OntvangenIntakeSoapOut">
<wsdl:part name="parameters" element="s0:OntvangenIntakeAntwoord" />
</wsdl:message>
<wsdl:message name="OntvangenIntakeStuurgegevens">
<wsdl:part name="Stuurgegevens" element="s0:Stuurgegevens" />
</wsdl:message>
<wsdl:portType name="IntakeService">
<wsdl:operation name="OntvangenIntake">
<wsdl:input message="tns:OntvangenIntakeSoapIn" />
<wsdl:output message="tns:OntvangenIntakeSoapOut" />
</wsdl:operation>
</wsdl:portType>
<wsdl:binding name="IntakeService" type="tns:IntakeService">
<soap:binding transport="http://schemas.xmlsoap.org/soap/http" />
<wsdl:operation name="OntvangenIntake">
<soap:operation soapAction="http://www.centric.nl/Publieksdiensten/Conductor/1.0/IntakeService/OntvangenIntake" style="document" />
<wsdl:input>
<soap:body use="literal" />
<soap:header message="tns:OntvangenIntakeStuurgegevens" part="Stuurgegevens" use="literal" />
</wsdl:input>
<wsdl:output>
<soap:body use="literal" />
</wsdl:output>
</wsdl:operation>
</wsdl:binding>
<wsdl:binding name="IntakeService1" type="tns:IntakeService">
<soap12:binding transport="http://schemas.xmlsoap.org/soap/http" />
<wsdl:operation name="OntvangenIntake">
<soap12:operation soapAction="http://www.centric.nl/Publieksdiensten/Conductor/1.0/IntakeService/OntvangenIntake" style="document" />
<wsdl:input>
<soap12:body use="literal" />
<soap12:header message="tns:OntvangenIntakeStuurgegevens" part="Stuurgegevens" use="literal" />
</wsdl:input>
<wsdl:output>
<soap12:body use="literal" />
</wsdl:output>
</wsdl:operation>
</wsdl:binding>
<wsdl:service name="Conductor_x0020_Intake_x0020_Service">
<wsdl:port name="IntakeService" binding="tns:IntakeService">
<soap:address location="'.$this->getParameter('app_url').'/api/simxml/zaken"/>
</wsdl:port>
<wsdl:port name="IntakeService1" binding="tns:IntakeService1">
<soap12:address location="https://109.109.118.17:443/opentunnel/00000001853051549000/sim/eform" />
</wsdl:port>
</wsdl:service>
</wsdl:definitions>
        ';

        return new Response(
            $wsdl,
            200,
            ['Content-Type' => 'application/wsdl+xml']
        );
    }
}
