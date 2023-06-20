<?php

namespace App\ActionHandler;

use App\Service\EmailService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class EmailHandler implements ActionHandlerInterface
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://commongateway.nl/ActionHandler/EmailHandler.ActionHandler.json',
            '$schema'    => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'      => 'EmailHandler',
            'required'   => ['ServiceDNS', 'template', 'sender', 'receiver', 'subject'],
            'properties' => [
                'serviceDNS' => [
                    'type'        => 'string',
                    'description' => 'The DNS of the mail provider, see https://symfony.com/doc/6.2/mailer.html for details',
                    'example'     => 'native://default',
                    'required'    => true,
                ],
                'template' => [
                    'type'        => 'string',
                    'description' => 'The actual email template, should be a base64 encoded twig template',
                    'example'     => 'eyMgdG9kbzogbW92ZSB0aGlzIHRvIGFuIGVtYWlsIHBsdWdpbiAoc2VlIEVtYWlsU2VydmljZS5waHApICN9CjwhRE9DVFlQRSBodG1sIFBVQkxJQyAiLS8vVzNDLy9EVEQgWEhUTUwgMS4wIFRyYW5zaXRpb25hbC8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9UUi94aHRtbDEvRFREL3hodG1sMS10cmFuc2l0aW9uYWwuZHRkIj4KPGh0bWwgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGh0bWwiPgo8aGVhZD4KICA8bWV0YSBuYW1lPSJ2aWV3cG9ydCIgY29udGVudD0id2lkdGg9ZGV2aWNlLXdpZHRoLCBpbml0aWFsLXNjYWxlPTEuMCIgLz4KICA8bWV0YSBodHRwLWVxdWl2PSJDb250ZW50LVR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD1VVEYtOCIgLz4KICA8dGl0bGU+e3sgc3ViamVjdCB9fTwvdGl0bGU+CgogIDxsaW5rIHJlbD0icHJlY29ubmVjdCIgaHJlZj0iaHR0cHM6Ly9mb250cy5nc3RhdGljLmNvbSIgLz4KICA8bGluawogICAgICAgICAgaHJlZj0iaHR0cHM6Ly9mb250cy5nb29nbGVhcGlzLmNvbS9jc3MyP2ZhbWlseT1GYXVzdGluYTp3Z2h0QDYwMCZkaXNwbGF5PXN3YXAiCiAgICAgICAgICByZWw9InN0eWxlc2hlZXQiCiAgLz4KICA8bGluawogICAgICAgICAgaHJlZj0iaHR0cHM6Ly9mb250cy5nb29nbGVhcGlzLmNvbS9jc3MyP2ZhbWlseT1Tb3VyY2UrU2FucytQcm8mZGlzcGxheT1zd2FwIgogICAgICAgICAgcmVsPSJzdHlsZXNoZWV0IgogIC8+CgogIDxzdHlsZSB0eXBlPSJ0ZXh0L2NzcyIgcmVsPSJzdHlsZXNoZWV0IiBtZWRpYT0iYWxsIj4KICAgIC8qIEJhc2UgLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovCgogICAgYm9keSB7CiAgICAgIHdpZHRoOiAxMDAlICFpbXBvcnRhbnQ7CiAgICAgIGhlaWdodDogMTAwJTsKICAgICAgbWFyZ2luOiAwOwogICAgICBtc28tbGluZS1oZWlnaHQtcnVsZTogZXhhY3RseTsKICAgICAgbGluZS1oZWlnaHQ6IDEuNDsKICAgICAgYmFja2dyb3VuZC1jb2xvcjogI2ZmZmZmZjsKICAgICAgY29sb3I6ICM3NDc4N2U7CiAgICAgIC13ZWJraXQtdGV4dC1zaXplLWFkanVzdDogbm9uZTsKICAgIH0KCiAgICBwLAogICAgdWwsCiAgICBvbCwKICAgIGJsb2NrcXVvdGUgewogICAgICBtc28tbGluZS1oZWlnaHQtcnVsZTogZXhhY3RseTsKICAgICAgbGluZS1oZWlnaHQ6IDEuNDsKICAgICAgdGV4dC1hbGlnbjogbGVmdDsKICAgIH0KCiAgICBhIHsKICAgICAgY29sb3I6ICMxZDU1ZmY7CiAgICAgIHRleHQtZGVjb3JhdGlvbjogbm9uZTsKICAgIH0KCiAgICBhOmhvdmVyIHsKICAgICAgdGV4dC1kZWNvcmF0aW9uOiB1bmRlcmxpbmU7CiAgICB9CgogICAgcCBhIHsKICAgICAgdGV4dC1kZWNvcmF0aW9uOiB1bmRlcmxpbmU7CiAgICB9CgogICAgYSBpbWcgewogICAgICBib3JkZXI6IG5vbmU7CiAgICB9CgogICAgdGQgewogICAgICB3b3JkLWJyZWFrOiBicmVhay13b3JkOwogICAgfQogICAgLyogTGF5b3V0IC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqLwoKICAgIC5oZWFkZXIgewogICAgICBiYWNrZ3JvdW5kOiAjMWQ1NWZmOwogICAgICB3aWR0aDogMTAwJTsKICAgICAgaGVpZ2h0OiAyMzZweDsKICAgICAgYmFja2dyb3VuZC1yZXBlYXQ6IG5vLXJlcGVhdDsKICAgICAgYmFja2dyb3VuZC1wb3NpdGlvbjogY2VudGVyOwogICAgfQoKICAgIC5oZWFkZXItY2VsbCB7CiAgICAgIHBhZGRpbmc6IDE2cHggMjRweDsKICAgIH0KCiAgICAuZW1haWwtd3JhcHBlciB7CiAgICAgIHdpZHRoOiAxMDAlOwogICAgICBtYXJnaW46IDA7CiAgICAgIHBhZGRpbmc6IDA7CiAgICAgIC1wcmVtYWlsZXItd2lkdGg6IDEwMCU7CiAgICAgIC1wcmVtYWlsZXItY2VsbHBhZGRpbmc6IDA7CiAgICAgIC1wcmVtYWlsZXItY2VsbHNwYWNpbmc6IDA7CiAgICAgIGJhY2tncm91bmQtY29sb3I6ICNmZmZmZmY7CiAgICB9CgogICAgLmVtYWlsLWNvbnRlbnQgewogICAgICB3aWR0aDogMTAwJTsKICAgICAgbWFyZ2luOiAwOwogICAgICBwYWRkaW5nOiAwOwogICAgICAtcHJlbWFpbGVyLXdpZHRoOiAxMDAlOwogICAgICAtcHJlbWFpbGVyLWNlbGxwYWRkaW5nOiAwOwogICAgICAtcHJlbWFpbGVyLWNlbGxzcGFjaW5nOiAwOwogICAgfQogICAgLyogTWFzdGhlYWQgLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi8KCiAgICAuZW1haWwtbWFzdGhlYWQgewogICAgICBwYWRkaW5nOiAyNXB4IDA7CiAgICAgIHRleHQtYWxpZ246IGNlbnRlcjsKICAgIH0KCiAgICAuZW1haWwtbWFzdGhlYWRfbG9nbyB7CiAgICAgIHdpZHRoOiA5NHB4OwogICAgfQoKICAgIC5lbWFpbC1tYXN0aGVhZF9uYW1lIHsKICAgICAgZm9udC1zaXplOiAxNnB4OwogICAgICBmb250LXdlaWdodDogNjAwOwogICAgICBjb2xvcjogI2JiYmZjMzsKICAgICAgdGV4dC1kZWNvcmF0aW9uOiBub25lOwogICAgICB0ZXh0LXNoYWRvdzogMCAxcHggMCB3aGl0ZTsKICAgIH0KICAgIC8qIEJvZHkgLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovCgogICAgLmVtYWlsLWJvZHkgewogICAgICB3aWR0aDogMTAwJTsKICAgICAgbWFyZ2luOiAwOwogICAgICBwYWRkaW5nOiAwOwogICAgICAtcHJlbWFpbGVyLXdpZHRoOiAxMDAlOwogICAgICAtcHJlbWFpbGVyLWNlbGxwYWRkaW5nOiAwOwogICAgICAtcHJlbWFpbGVyLWNlbGxzcGFjaW5nOiAwOwogICAgICBiYWNrZ3JvdW5kOiBub25lOwogICAgfQoKICAgIC5lbWFpbC1ib2R5X2lubmVyIHsKICAgICAgd2lkdGg6IDY0MHB4OwogICAgICBtYXJnaW46IDAgYXV0bzsKICAgICAgcGFkZGluZzogMDsKICAgICAgLXByZW1haWxlci13aWR0aDogNTcwcHg7CiAgICAgIC1wcmVtYWlsZXItY2VsbHBhZGRpbmc6IDA7CiAgICAgIC1wcmVtYWlsZXItY2VsbHNwYWNpbmc6IDA7CiAgICAgIGJhY2tncm91bmQtY29sb3I6ICNmZmZmZmY7CiAgICB9CgogICAgLmVtYWlsLWZvb3RlciB7CiAgICAgIHdpZHRoOiA2NDBweDsKICAgICAgbWFyZ2luOiAwIGF1dG87CiAgICAgIHBhZGRpbmc6IDA7CiAgICAgIC1wcmVtYWlsZXItd2lkdGg6IDU3MHB4OwogICAgICAtcHJlbWFpbGVyLWNlbGxwYWRkaW5nOiAwOwogICAgICAtcHJlbWFpbGVyLWNlbGxzcGFjaW5nOiAwOwogICAgICB0ZXh0LWFsaWduOiBjZW50ZXI7CiAgICB9CgogICAgLmVtYWlsLWZvb3RlciBwIHsKICAgICAgY29sb3I6ICNhZWFlYWU7CiAgICB9CgogICAgLmJvZHktYWN0aW9uIHsKICAgICAgd2lkdGg6IDEwMCU7CiAgICAgIG1hcmdpbjogNDBweCBhdXRvOwogICAgICBwYWRkaW5nOiAwOwogICAgICAtcHJlbWFpbGVyLXdpZHRoOiAxMDAlOwogICAgICAtcHJlbWFpbGVyLWNlbGxwYWRkaW5nOiAwOwogICAgICAtcHJlbWFpbGVyLWNlbGxzcGFjaW5nOiAwOwogICAgICB0ZXh0LWFsaWduOiBjZW50ZXI7CiAgICB9CgogICAgLmJvZHktc3ViIHsKICAgICAgbWFyZ2luLXRvcDogMjVweDsKICAgICAgcGFkZGluZy10b3A6IDI1cHg7CiAgICAgIGJvcmRlci10b3A6IDFweCBzb2xpZCAjZWRlZmYyOwogICAgfQoKICAgIC5jb250ZW50LWNlbGwgewogICAgICBwYWRkaW5nOiAzNnB4IDE2cHg7CiAgICB9CgogICAgLnByZWhlYWRlciB7CiAgICAgIGRpc3BsYXk6IG5vbmUgIWltcG9ydGFudDsKICAgICAgdmlzaWJpbGl0eTogaGlkZGVuOwogICAgICBtc28taGlkZTogYWxsOwogICAgICBmb250LXNpemU6IDFweDsKICAgICAgbXNvLWxpbmUtaGVpZ2h0LXJ1bGU6IGV4YWN0bHk7CiAgICAgIGxpbmUtaGVpZ2h0OiAxcHg7CiAgICAgIG1heC1oZWlnaHQ6IDA7CiAgICAgIG1heC13aWR0aDogMDsKICAgICAgb3BhY2l0eTogMDsKICAgICAgb3ZlcmZsb3c6IGhpZGRlbjsKICAgIH0KICAgIC8qIEF0dHJpYnV0ZSBsaXN0IC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqLwoKICAgIC5hdHRyaWJ1dGVzIHsKICAgICAgbWFyZ2luOiAwIDAgMjFweDsKICAgIH0KCiAgICAuYXR0cmlidXRlc19jb250ZW50IHsKICAgICAgYmFja2dyb3VuZC1jb2xvcjogI2VkZWZmMjsKICAgICAgcGFkZGluZzogMTZweDsKICAgIH0KCiAgICAuYXR0cmlidXRlc19pdGVtIHsKICAgICAgcGFkZGluZzogMDsKICAgIH0KICAgIC8qIFJlbGF0ZWQgSXRlbXMgLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovCgogICAgLnJlbGF0ZWQgewogICAgICB3aWR0aDogMTAwJTsKICAgICAgbWFyZ2luOiAwOwogICAgICBwYWRkaW5nOiAyNXB4IDAgMCAwOwogICAgICAtcHJlbWFpbGVyLXdpZHRoOiAxMDAlOwogICAgICAtcHJlbWFpbGVyLWNlbGxwYWRkaW5nOiAwOwogICAgICAtcHJlbWFpbGVyLWNlbGxzcGFjaW5nOiAwOwogICAgfQoKICAgIC5yZWxhdGVkX2l0ZW0gewogICAgICBwYWRkaW5nOiAxMHB4IDA7CiAgICAgIGNvbG9yOiAjNzQ3ODdlOwogICAgICBmb250LXNpemU6IDE1cHg7CiAgICAgIG1zby1saW5lLWhlaWdodC1ydWxlOiBleGFjdGx5OwogICAgICBsaW5lLWhlaWdodDogMThweDsKICAgIH0KCiAgICAucmVsYXRlZF9pdGVtLXRpdGxlIHsKICAgICAgZGlzcGxheTogYmxvY2s7CiAgICAgIG1hcmdpbjogMC41ZW0gMCAwOwogICAgfQoKICAgIC5yZWxhdGVkX2l0ZW0tdGh1bWIgewogICAgICBkaXNwbGF5OiBibG9jazsKICAgICAgcGFkZGluZy1ib3R0b206IDEwcHg7CiAgICB9CgogICAgLnJlbGF0ZWRfaGVhZGluZyB7CiAgICAgIGJvcmRlci10b3A6IDFweCBzb2xpZCAjZWRlZmYyOwogICAgICB0ZXh0LWFsaWduOiBjZW50ZXI7CiAgICAgIHBhZGRpbmc6IDI1cHggMCAxMHB4OwogICAgfQoKICAgIC8qIFV0aWxpdGllcyAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi8KCiAgICAubm8tbWFyZ2luIHsKICAgICAgbWFyZ2luOiAwOwogICAgfQoKICAgIC5tYXJnaW4tdG9wIHsKICAgICAgbWFyZ2luLXRvcDogOHB4OwogICAgfQoKICAgIC5hbGlnbi1yaWdodCB7CiAgICAgIHRleHQtYWxpZ246IHJpZ2h0OwogICAgfQoKICAgIC5hbGlnbi1sZWZ0IHsKICAgICAgdGV4dC1hbGlnbjogbGVmdDsKICAgIH0KCiAgICAuYWxpZ24tY2VudGVyIHsKICAgICAgdGV4dC1hbGlnbjogY2VudGVyOwogICAgfQogICAgLypNZWRpYSBRdWVyaWVzIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqLwoKICAgIEBtZWRpYSBvbmx5IHNjcmVlbiBhbmQgKG1heC13aWR0aDogNjAwcHgpIHsKICAgICAgLmVtYWlsLWJvZHlfaW5uZXIsCiAgICAgIC5lbWFpbC1mb290ZXIgewogICAgICAgIHdpZHRoOiAxMDAlICFpbXBvcnRhbnQ7CiAgICAgIH0KICAgIH0KCiAgICBAbWVkaWEgb25seSBzY3JlZW4gYW5kIChtYXgtd2lkdGg6IDUwMHB4KSB7CiAgICAgIC5idXR0b24gewogICAgICAgIHdpZHRoOiAxMDAlICFpbXBvcnRhbnQ7CiAgICAgIH0KICAgIH0KCiAgICAvKiBDYXJkcyAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi8KICAgIC5jYXJkIHsKICAgICAgYmFja2dyb3VuZC1jb2xvcjogI2ZmZjsKICAgICAgYm9yZGVyLXRvcDogMXB4IHNvbGlkICNlMGUxZTU7CiAgICAgIGJvcmRlci1yaWdodDogMXB4IHNvbGlkICNlMGUxZTU7CiAgICAgIGJvcmRlci1ib3R0b206IDFweCBzb2xpZCAjZTBlMWU1OwogICAgICBib3JkZXItbGVmdDogMXB4IHNvbGlkICNlMGUxZTU7CiAgICAgIHBhZGRpbmc6IDI0cHg7CiAgICAgIGRpc3BsYXk6IGlubGluZS1ibG9jazsKICAgICAgY29sb3I6ICMzOTM5M2E7CiAgICAgIHRleHQtZGVjb3JhdGlvbjogbm9uZTsKICAgICAgd2lkdGg6IDEwMCU7CiAgICAgIGJvcmRlci1yYWRpdXM6IDNweDsKICAgICAgYm94LXNoYWRvdzogMCA0cHggM3B4IC0zcHggcmdiYSgwLCAwLCAwLCAwLjA4KTsKICAgICAgLXdlYmtpdC10ZXh0LXNpemUtYWRqdXN0OiBub25lOwogICAgICBtc28tbGluZS1oZWlnaHQtcnVsZTogZXhhY3RseTsKICAgICAgbGluZS1oZWlnaHQ6IDEuNzU7CiAgICAgIGxldHRlci1zcGFjaW5nOiAwLjhweDsKICAgIH0KCiAgICAvKiBCdXR0b25zIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLSAqLwoKICAgIC5idXR0b24gewogICAgICBiYWNrZ3JvdW5kLWNvbG9yOiAjMWRiNGVkOwogICAgICBib3JkZXItdG9wOiAxMHB4IHNvbGlkICMxZGI0ZWQ7CiAgICAgIGJvcmRlci1yaWdodDogMThweCBzb2xpZCAjMWRiNGVkOwogICAgICBib3JkZXItYm90dG9tOiAxMHB4IHNvbGlkICMxZGI0ZWQ7CiAgICAgIGJvcmRlci1sZWZ0OiAxOHB4IHNvbGlkICMxZGI0ZWQ7CiAgICAgIGRpc3BsYXk6IGlubGluZS1ibG9jazsKICAgICAgY29sb3I6ICNmZmY7CiAgICAgIHRleHQtZGVjb3JhdGlvbjogbm9uZTsKICAgICAgYm9yZGVyLXJhZGl1czogNHB4OwogICAgICBib3gtc2hhZG93OiAwIDJweCAzcHggcmdiYSgwLCAwLCAwLCAwLjE2KTsKICAgICAgLXdlYmtpdC10ZXh0LXNpemUtYWRqdXN0OiBub25lOwogICAgICBtc28tbGluZS1oZWlnaHQtcnVsZTogZXhhY3RseTsKICAgICAgd2lkdGg6IDEwMCU7CiAgICAgIHRleHQtYWxpZ246IGNlbnRlcjsKICAgICAgZm9udC1zaXplOiAxNHB4OwogICAgICBmb250LXdlaWdodDogNjAwOwogICAgfQoKICAgIC5zbWFsbC1sb2dvIHsKICAgICAgd2lkdGg6IDI0cHg7CiAgICAgIGhlaWdodDogMjRweDsKICAgIH0KCiAgICAuaW5saW5lIHsKICAgICAgZGlzcGxheTogaW5saW5lOwogICAgfQogICAgLyogVHlwZSAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0gKi8KCiAgICBwIHsKICAgICAgbWFyZ2luOiAwOwogICAgICBjb2xvcjogIzM5MzkzYTsKICAgICAgZm9udC1zaXplOiAxNXB4OwogICAgICBtc28tbGluZS1oZWlnaHQtcnVsZTogZXhhY3RseTsKICAgICAgbGV0dGVyLXNwYWNpbmc6IG5vcm1hbDsKICAgICAgdGV4dC1hbGlnbjogbGVmdDsKICAgICAgbGluZS1oZWlnaHQ6IDIwcHg7CiAgICB9CgogICAgcCArIHAgewogICAgICBtYXJnaW4tdG9wOiAyMHB4OwogICAgfQoKICAgIHAuc3VmZml4IHsKICAgICAgZm9udC1zaXplOiAxNHB4OwogICAgfQoKICAgIHAuc3ViIHsKICAgICAgZm9udC1zaXplOiAxMnB4OwogICAgfQoKICAgIHAuY2VudGVyIHsKICAgICAgdGV4dC1hbGlnbjogY2VudGVyOwogICAgfQoKICAgIC5zdWJ0bGUgewogICAgICBjb2xvcjogI2IxYjFiMTsKICAgIH0KCiAgICAvKiBGb290ZXIgLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovCgogICAgLmxvZ28tbGFiZWwgewogICAgICB2ZXJ0aWNhbC1hbGlnbjogdG9wOwogICAgICBmb250LXNpemU6IDE0cHg7CiAgICAgIG1hcmdpbi1sZWZ0OiA0cHg7CiAgICB9CgogICAgLmZvb3Rlci1jZWxsIHsKICAgICAgcGFkZGluZzogOHB4IDI0cHg7CiAgICB9CgogICAgLmZvb3Rlci1uYXYgewogICAgICBtYXJnaW4tbGVmdDogOHB4OwogICAgICBmb250LXNpemU6IDE0cHg7CiAgICAgIGNvbG9yOiAjMzkzOTNhOwogICAgICB0ZXh0LWRlY29yYXRpb246IG5vbmU7CiAgICB9CgogICAgLmhlYWRlci1saW5rIHsKICAgICAgdGV4dC1kZWNvcmF0aW9uOiBub25lOwogICAgICBmb250LXNpemU6IDE0cHg7CiAgICAgIGNvbG9yOiAjMWQ1NWZmOwogICAgICBmb250LXdlaWdodDogNTAwOwogICAgfQoKICAgIC5tYXJnaW4tdG9wIHsKICAgICAgbWFyZ2luLXRvcDogMTZweDsKICAgIH0KCiAgICAubG9nby1jb250YWluZXIgewogICAgICB3aWR0aDogMTAwJTsKICAgICAgbWFyZ2luLWJvdHRvbTogNTZweDsKICAgIH0KCiAgICAubG9nbyB7CiAgICAgIGRpc3BsYXk6IGJsb2NrOwogICAgfQoKICAgIC8qIEN1c3RvbSBzdHlsZXMgLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tICovCiAgICBociB7CiAgICAgIGJvcmRlci10b3A6IDFweCBzb2xpZCAjZDlkOWRlOwogICAgICBjb2xvcjogI2Q5ZDlkZTsKICAgICAgYmFja2dyb3VuZC1jb2xvcjogI2Q5ZDlkZTsKICAgICAgbWFyZ2luLXRvcDogMzJweDsKICAgICAgbWFyZ2luLWJvdHRvbTogNDBweDsKICAgIH0KCiAgICBoMSB7CiAgICAgIGZvbnQtZmFtaWx5OiAiRmF1c3RpbmEiLCBzZXJpZjsKICAgICAgZm9udC1zaXplOiAzMnB4OwogICAgICBmb250LXdlaWdodDogNjAwOwogICAgICBjb2xvcjogIzIzMjMyNjsKICAgICAgbWFyZ2luLWJvdHRvbTogMjJweDsKICAgIH0KCiAgICBwIHsKICAgICAgZm9udC1mYW1pbHk6ICJTb3VyY2UgU2FucyBQcm8iLCBzYW5zLXNlcmlmOwogICAgICBmb250LXNpemU6IDE4cHg7CiAgICAgIGxpbmUtaGVpZ2h0OiAxLjY7CiAgICAgIGNvbG9yOiAjMjMyMzI2OwogICAgfQoKICAgIC5idXR0b24gewogICAgICBmb250LWZhbWlseTogIlNvdXJjZSBTYW5zIFBybyIsIHNhbnMtc2VyaWY7CiAgICB9CgogICAgLmNvbnRlbnQtY2VsbCB7CiAgICAgIHBhZGRpbmc6IDQwcHggNDBweDsKICAgIH0KCiAgICAuYnV0dG9uIHsKICAgICAgYmFja2dyb3VuZC1jb2xvcjogI2ZmNWEyNjsKICAgICAgYm9yZGVyLXRvcDogMTBweCBzb2xpZCAjZmY1YTI2OwogICAgICBib3JkZXItcmlnaHQ6IDE4cHggc29saWQgI2ZmNWEyNjsKICAgICAgYm9yZGVyLWJvdHRvbTogMTBweCBzb2xpZCAjZmY1YTI2OwogICAgICBib3JkZXItbGVmdDogMThweCBzb2xpZCAjZmY1YTI2OwogICAgICBkaXNwbGF5OiBpbmxpbmUtYmxvY2s7CiAgICAgIGNvbG9yOiAjZmZmOwogICAgICB3aWR0aDogYXV0bzsKICAgICAgYm94LXNoYWRvdzogbm9uZTsKICAgICAgdGV4dC1kZWNvcmF0aW9uOiBub25lOwogICAgICBib3JkZXItcmFkaXVzOiA4cHg7CiAgICAgIC13ZWJraXQtdGV4dC1zaXplLWFkanVzdDogbm9uZTsKICAgICAgbXNvLWxpbmUtaGVpZ2h0LXJ1bGU6IGV4YWN0bHk7CiAgICAgIHRleHQtYWxpZ246IGNlbnRlcjsKICAgICAgZm9udC1zaXplOiAxNHB4OwogICAgICBmb250LXdlaWdodDogNjAwOwogICAgfQogIDwvc3R5bGU+CjwvaGVhZD4KPGJvZHk+Cjx0YWJsZSBjbGFzcz0iZW1haWwtd3JhcHBlciIgd2lkdGg9IjEwMCUiIGNlbGxwYWRkaW5nPSIwIiBjZWxsc3BhY2luZz0iMCI+CiAgPHRyPgogICAgPHRkIGFsaWduPSJjZW50ZXIiPgogICAgICA8dGFibGUKICAgICAgICAgICAgICBjbGFzcz0iZW1haWwtY29udGVudCIKICAgICAgICAgICAgICB3aWR0aD0iMTAwJSIKICAgICAgICAgICAgICBjZWxscGFkZGluZz0iMCIKICAgICAgICAgICAgICBjZWxsc3BhY2luZz0iMCIKICAgICAgPgogICAgICAgIDx0cj4KICAgICAgICAgIDx0ZCBjbGFzcz0iZW1haWwtbWFzdGhlYWQiPjwvdGQ+CiAgICAgICAgPC90cj4KICAgICAgICA8IS0tIEVtYWlsIEJvZHkgLS0+CiAgICAgICAgPHRyPgogICAgICAgICAgPHRkCiAgICAgICAgICAgICAgICAgIGNsYXNzPSJlbWFpbC1ib2R5IgogICAgICAgICAgICAgICAgICB3aWR0aD0iMTAwJSIKICAgICAgICAgICAgICAgICAgY2VsbHBhZGRpbmc9IjAiCiAgICAgICAgICAgICAgICAgIGNlbGxzcGFjaW5nPSIwIgogICAgICAgICAgPgogICAgICAgICAgICA8dGFibGUKICAgICAgICAgICAgICAgICAgICBjbGFzcz0iZW1haWwtYm9keV9pbm5lciIKICAgICAgICAgICAgICAgICAgICBhbGlnbj0iY2VudGVyIgogICAgICAgICAgICAgICAgICAgIHdpZHRoPSIxMDAlIgogICAgICAgICAgICAgICAgICAgIGJhY2tncm91bmQtY29sb3I9IiNlZGVmZjIiCiAgICAgICAgICAgICAgICAgICAgY2VsbHBhZGRpbmc9IjAiCiAgICAgICAgICAgICAgICAgICAgY2VsbHNwYWNpbmc9IjAiCiAgICAgICAgICAgID4KICAgICAgICAgICAgICA8IS0tIEJvZHkgY29udGVudCAtLT4KICAgICAgICAgICAgICA8dHI+PC90cj4KICAgICAgICAgICAgICA8dHI+CiAgICAgICAgICAgICAgICA8dGQgY2xhc3M9ImNvbnRlbnQtY2VsbCIgd2lkdGg9IjEwMCUiPgogICAgICAgICAgICAgICAgICA8cD4KICAgICAgICAgICAgICAgICAgICAgIHt7IGF1dGhvciB9fSBoZWVmdCBmZWVkYmFjayBnZWdldmVuIG9wOiB7eyB0b3BpYyB9fQogICAgICAgICAgICAgICAgICA8L3A+CiAgICAgICAgICAgICAgICAgIDxici8+CiAgICAgICAgICAgICAgICAgIDxwPgogICAgICAgICAgICAgICAgICAgICAge3sgZGVzY3JpcHRpb24gfCBubDJiciB9fQogICAgICAgICAgICAgICAgICA8L3A+CiAgICAgICAgICAgICAgICAgIDxociAvPgogICAgICAgICAgICAgICAgICA8cD5NZXQgdnJpZW5kZWxpamtlIGdyb2V0LDwvcD4KICAgICAgICAgICAgICAgICAgPHA+S0lTUzwvcD4KICAgICAgICAgICAgICAgIDwvdGQ+CiAgICAgICAgICAgICAgPC90cj4KICAgICAgICAgICAgICA8dHI+PC90cj4KICAgICAgICAgICAgPC90YWJsZT4KICAgICAgICAgIDwvdGQ+CiAgICAgICAgPC90cj4KICAgICAgPC90YWJsZT4KICAgIDwvdGQ+CiAgPC90cj4KPC90YWJsZT4KPC9ib2R5Pgo8L2h0bWw+Cg==',
                    'required'    => true,
                ],
                'variables' => [
                    'type'        => 'array',
                    'description' => 'The variables supported by this template (might contain default vallues)',
                    'nullable'    => true,
                ],
                'sender' => [
                    'type'        => 'string',
                    'description' => 'The sender of the email',
                    'example'     => 'info@conduction.nl',
                    'required'    => true,
                ],
                'receiver' => [
                    'type'        => 'string',
                    'description' => 'The receiver of the email',
                    'example'     => 'j.do@conduction.nl',
                    'required'    => true,
                ],
                'subject' => [
                    'type'        => 'string',
                    'description' => 'The subject of the email',
                    'example'     => 'Your weekly update',
                    'required'    => true,
                ],
                'cc' => [
                    'type'        => 'string',
                    'description' => 'Carbon copy, email boxes that should receive a copy of  this mail',
                    'example'     => 'archive@conduction.nl',
                    'nullable'    => true,
                ],
                'bcc' => [
                    'type'        => 'string',
                    'description' => 'Blind carbon copy, people that should receive a copy without other recipient knowing',
                    'example'     => 'b.brother@conduction.nl',
                    'nullable'    => true,
                ],
                'replyTo' => [
                    'type'        => 'string',
                    'description' => 'The address the receiver should reply to, only provide this if it differs from the sender address',
                    'example'     => 'no-reply@conduction.nl',
                    'nullable'    => true,
                ],
                'priority' => [
                    'type'        => 'int',
                    'description' => 'An optional priority for the email',
                    'nullable'    => true,
                ],
            ],
        ];
    }

    /**
     * This function runs the email service plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws TransportExceptionInterface|LoaderError|RuntimeError|SyntaxError
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->emailService->emailHandler($data, $configuration);
    }
}
