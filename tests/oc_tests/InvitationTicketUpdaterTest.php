<?php

declare(strict_types=1);

use App\Services\OpenChat\OpenChatHourlyInvitationTicketUpdater;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use PHPUnit\Framework\TestCase;

class InvitationTicketUpdaterTest extends TestCase
{
    private OpenChatHourlyInvitationTicketUpdater $inst;

    public function test()
    {
        $this->inst = app(OpenChatHourlyInvitationTicketUpdater::class);
        $res = $this->inst->updateInvitationTicket(164927, 'gsIbu5PEPsmS7D0OKm_iwkQravvNFrahZuZyh-SzJ0tiFg-D6xT75wkDm9I');

        $this->assertTrue($res);
    }
}
