<?php

namespace App\Domain\FileStorage\Drive;

enum WorkspaceTransferReservationSide: string
{
    case Source = 'SOURCE';
    case Destination = 'DESTINATION';
}
