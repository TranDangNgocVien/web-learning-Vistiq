<?php

namespace VistiqCore\Services\Shared;

use Illuminate\Validation\DatabasePresenceVerifier;

class CompactPresenceVerifier extends DatabasePresenceVerifier
{
    protected function table($table)
    {
        return CompactDatabase::definition($table)
            ? CompactDatabase::table($table)->useWritePdo()
            : parent::table($table);
    }
}
