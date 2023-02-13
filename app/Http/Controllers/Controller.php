<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function ok(){
        $query = $query->where(static function ($queryForStaffType) use ($military_kadr_structure, $sessionUser) {
            return $queryForStaffType->where(function ($query) use ($sessionUser, $military_kadr_structure) {
                //Staff type === 3
                return $query->where('s.staff_type', 3)->orWhere(function () use ($legal_town_prosecutors, $structures_three, $legal_three_condition, $military_kadr_structure, $sessionUser) {
                    if (($sessionUser->user_info->is_chief || $sessionUser->user_info->is_town_prosecutor
                        || $sessionUser->user_info->position_type == 10 || $sessionUser->user_info->structure_ordinary == 10
                        || $sessionUser->user_info->position_type == 29 ||
                        ($military_kadr_structure ? $sessionUser->user_info->structure_id == $military_kadr_structure->id : false))) {


                        if ($sessionUser->user_info->position_type == 10) {
                            $query = $query->where(function ($q) use ($sessionUser) {
                                return $q->where("spv.next_position", "head")->orWhere("curator_id", $sessionUser->authStaffId);
                            })
                                ->orWhere('refunded_by', $sessionUser->authStaffId)->orWhere('rejected_by', $sessionUser->authStaffId);
                        } elseif ($sessionUser->user_info->position_type == 29) {


                            $query = $query
                                ->where(function ($t) use ($legal_three_condition, $structures_three, $legal_town_prosecutors) {
                                    return $t->whereIn('s.structure_id', $structures_three)->orWhere('s.organization_id', $legal_three_condition)->orWhere('s.organization_id', $legal_town_prosecutors);
                                })->where(function ($q1) use ($sessionUser, $legal_baku_town_curation, $structures_baku_town_curation) {

                                    return $q1
                                        ->where(function ($q) use ($sessionUser, $legal_baku_town_curation, $structures_baku_town_curation) {

                                            //Baki seher prokuroru + muavinleri + baki seher rayon prokurorlari istisna olmaqla diger prokurorlar
                                            return $q->where(function ($q3) use ($sessionUser, $legal_baku_town_curation, $structures_baku_town_curation) {
                                                return $q3->whereNotIn("s.position", [29, 31])
                                                    ->orWhereNotIn("s.organization_id", $legal_baku_town_curation)
                                                    ->orWhereNotIn("s.organization_id", $structures_baku_town_curation);
                                            })->whereIn("next_position", ["baku_prosecutor", "kadr_command_number"]);

                                        })
                                        ->orWhere(function ($q) use ($legal_baku_town_curation, $structures_baku_town_curation) {

                                            //Baki seher prokurorun muavinleri + baki seher rayon prokurorlari nisbetde
                                            return $q->where(function ($q3) use ($legal_baku_town_curation, $structures_baku_town_curation) {
                                                return $q3->whereIn("s.position", [29, 31])
                                                    ->orWhereIn("s.organization_id", $legal_baku_town_curation)
                                                    ->orWhereIn("s.organization_id", $structures_baku_town_curation);
                                            })->whereIn("next_position", ["head_prosecutor", "kadr_command_number"]);

                                        });
                                });


                        } elseif ($sessionUser->user_info->is_town_prosecutor || $sessionUser->user_info->is_chief) {
                            $structure = $sessionUser->user_info->structure_id;
                            $legal = $sessionUser->user_info->organization_id;
                            $staffs = null;
                            if ($structure) {
                                $staffs = Staff::query()->where('structure_id', $structure)->get(); // Chief
                            } else {
                                $staffs = Staff::query()->where('organization_id', $legal)->get(); // town
                            }
                            $query = $query->where(static function ($q) use ($legal_three_condition, $legal_town_prosecutors, $legal_baku_town_curation, $structures_baku_town_curation, $structures_head_prosecutor, $legal_head_prosecutor, $sessionUser) {
                                $creator = Staff::query()->from("staff as s")
                                    ->select([
                                        "s.id",
                                        "s.position",
                                        "s.organization_id",
                                        "s.structure_id",
                                        "s.staff_type"
                                    ])
                                    ->where("id", $sessionUser->staffId)->firstOrFail();
                            });
                            $query = $query->where(function ($row) use ($sessionUser, $staffs) {
                                return $row->whereIn('spv.created_by', $staffs->pluck('id'))->whereIn('next_position', ['town_or_chief', 'kadr_command']);
                            })->orWhere('refunded_by', $sessionUser->authStaffId)->orWhere('rejected_by', $sessionUser->authStaffId);
                        } elseif ($sessionUser->user_info->position_type == 29) {
                            $structure = $sessionUser->user_info->structure_id;
                            $legal = $sessionUser->user_info->organization_id;

                            $query = $query->where(function ($row) use ($staffs, $sessionUser) {
                                return $row->whereIn('spv.created_by', $staffs->pluck('id'))
                                    ->whereIn('next_position', ['town_or_chief']);
                            })->orWhere('refunded_by', $sessionUser->authStaffId)->orWhere('rejected_by', $sessionUser->authStaffId);
                        }
                    }
                });
            });

            return $queryForStaffType->where(function ($query) use ($sessionUser, $military_kadr_structure) {
                //Staff type === 3
                return $query->where('s.staff_type', '!=', 3)->orWhere(function () use ($legal_town_prosecutors, $structures_three, $legal_three_condition, $military_kadr_structure, $sessionUser) {
                    if (($sessionUser->user_info->is_chief || $sessionUser->user_info->is_town_prosecutor
                        || $sessionUser->user_info->position_type == 10 || $sessionUser->user_info->structure_ordinary == 10
                        || $sessionUser->user_info->position_type == 29 ||
                        ($military_kadr_structure ? $sessionUser->user_info->structure_id == $military_kadr_structure->id : false))) {


                        if ($sessionUser->user_info->position_type == 10) {
                            $query = $query->where(function ($q) use ($sessionUser) {
                                return $q->where("spv.next_position", "head")->orWhere("curator_id", $sessionUser->authStaffId);
                            })
                                ->orWhere('refunded_by', $sessionUser->authStaffId)->orWhere('rejected_by', $sessionUser->authStaffId);
                        } elseif ($sessionUser->user_info->position_type == 29) {


                            $query = $query
                                ->where(function ($t) use ($legal_three_condition, $structures_three, $legal_town_prosecutors) {
                                    return $t->whereIn('s.structure_id', $structures_three)->orWhere('s.organization_id', $legal_three_condition)->orWhere('s.organization_id', $legal_town_prosecutors);
                                })->where(function ($q1) use ($sessionUser, $legal_baku_town_curation, $structures_baku_town_curation) {

                                    return $q1
                                        ->where(function ($q) use ($sessionUser, $legal_baku_town_curation, $structures_baku_town_curation) {

                                            //Baki seher prokuroru + muavinleri + baki seher rayon prokurorlari istisna olmaqla diger prokurorlar
                                            return $q->where(function ($q3) use ($sessionUser, $legal_baku_town_curation, $structures_baku_town_curation) {
                                                return $q3->whereNotIn("s.position", [29, 31])
                                                    ->orWhereNotIn("s.organization_id", $legal_baku_town_curation)
                                                    ->orWhereNotIn("s.organization_id", $structures_baku_town_curation);
                                            })->whereIn("next_position", ["baku_prosecutor", "kadr_command_number"]);

                                        })
                                        ->orWhere(function ($q) use ($legal_baku_town_curation, $structures_baku_town_curation) {

                                            //Baki seher prokurorun muavinleri + baki seher rayon prokurorlari nisbetde
                                            return $q->where(function ($q3) use ($legal_baku_town_curation, $structures_baku_town_curation) {
                                                return $q3->whereIn("s.position", [29, 31])
                                                    ->orWhereIn("s.organization_id", $legal_baku_town_curation)
                                                    ->orWhereIn("s.organization_id", $structures_baku_town_curation);
                                            })->whereIn("next_position", ["head_prosecutor", "kadr_command_number"]);

                                        });
                                });


                        } elseif ($sessionUser->user_info->is_town_prosecutor || $sessionUser->user_info->is_chief) {
                            $structure = $sessionUser->user_info->structure_id;
                            $legal = $sessionUser->user_info->organization_id;
                            $staffs = null;
                            if ($structure) {
                                $staffs = Staff::query()->where('structure_id', $structure)->get(); // Chief
                            } else {
                                $staffs = Staff::query()->where('organization_id', $legal)->get(); // town
                            }
                            $query = $query->where(static function ($q) use ($legal_three_condition, $legal_town_prosecutors, $legal_baku_town_curation, $structures_baku_town_curation, $structures_head_prosecutor, $legal_head_prosecutor, $sessionUser) {
                                $creator = Staff::query()->from("staff as s")
                                    ->select([
                                        "s.id",
                                        "s.position",
                                        "s.organization_id",
                                        "s.structure_id",
                                        "s.staff_type"
                                    ])
                                    ->where("id", $sessionUser->staffId)->firstOrFail();
                            });
                            $query = $query->where(function ($row) use ($sessionUser, $staffs) {
                                return $row->whereIn('spv.created_by', $staffs->pluck('id'))->whereIn('next_position', ['town_or_chief', 'kadr_command']);
                            })->orWhere('refunded_by', $sessionUser->authStaffId)->orWhere('rejected_by', $sessionUser->authStaffId);
                        } elseif ($sessionUser->user_info->position_type == 29) {
                            $structure = $sessionUser->user_info->structure_id;
                            $legal = $sessionUser->user_info->organization_id;

                            $query = $query->where(function ($row) use ($staffs, $sessionUser) {
                                return $row->whereIn('spv.created_by', $staffs->pluck('id'))
                                    ->whereIn('next_position', ['town_or_chief']);
                            }

    
}
