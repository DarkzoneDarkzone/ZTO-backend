<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FiltersOperator {
    public function FiltersOperators($filters){
        [$field, $operator, $value] = $filters;
        $eloQuery = [];
        switch ($operator) {
            case 'eq':
                $eloQuery[] = [$field, '=', $value];
                break;
            case 'neq':
                $eloQuery[] = [$field, '!=', $value];
                break;
            case 'lt':
                $eloQuery[] = [$field, '<', $value];
                break;
            case 'gt':
                $eloQuery[] = [$field, '>', $value];
                break;
            case 'lte':
                $eloQuery[] = [$field, '<=', $value];
                break;
            case 'gte':
                $eloQuery[] = [$field, '>=', $value];
                break;
            case 'like':
                $eloQuery[] = [$field, 'like', '%' . $value . '%'];
                break;
        }
        return $eloQuery;
    }
}