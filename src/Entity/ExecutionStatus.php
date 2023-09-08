<?php

namespace App\Entity;

enum ExecutionStatus: string
{
    case Running = 'running';
    case Finished = 'finished';
}