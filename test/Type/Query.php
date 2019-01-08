<?
namespace Type;

class Query
{
    public function User($root, $args, $context)
    {
        if ($id) {
            $w[] = ["user_id=?", $id];
        }
        return \App\User::Find()->asArray();
    }
}
