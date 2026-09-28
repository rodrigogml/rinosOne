<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuthorizationAccessRequest extends Model { protected $table = 'auth_access_request'; public const CREATED_AT = 'createdAt'; public const UPDATED_AT = 'updatedAt'; protected $fillable = ['idRequesterUser','idRecipientUser','idPermission','idTenant','idApproverUser','scope','startsAt','endsAt','state','decidedAt']; protected function casts(): array { return ['startsAt'=>'datetime','endsAt'=>'datetime','decidedAt'=>'datetime']; } }
