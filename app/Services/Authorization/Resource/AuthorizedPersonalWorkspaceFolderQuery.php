<?php

namespace App\Services\Authorization\Resource;

use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Lists active personal workspace folders visible through workspace access or read relations.
 *
 * The database resolves direct/group subjects and descendant inheritance in one query, so callers
 * never enumerate candidates and invoke individual authorization decisions.
 */
class AuthorizedPersonalWorkspaceFolderQuery
{
    /** @return Collection<int, WorkspaceFolder> */
    public function pageFor(User $principal, int $page, int $perPage): Collection
    {
        $offset = ($page - 1) * $perPage;

        return WorkspaceFolder::fromQuery(
            <<<'SQL'
                WITH RECURSIVE eligible_group(id) AS (
                    SELECT direct_group.id
                    FROM auth_group_user
                    INNER JOIN auth_group direct_group ON direct_group.id = auth_group_user.idGroup
                    WHERE auth_group_user.idUser = ?
                        AND direct_group.active = 1
                        AND direct_group.scope = 'PERSONAL'
                        AND direct_group.idTenant IS NULL
                    UNION
                    SELECT relation.idParentGroup
                    FROM auth_group_group relation
                    INNER JOIN eligible_group ON eligible_group.id = relation.idChildGroup
                    INNER JOIN auth_group parent_group ON parent_group.id = relation.idParentGroup
                    WHERE parent_group.active = 1
                        AND parent_group.scope = 'PERSONAL'
                        AND parent_group.idTenant IS NULL
                ),
                shared_root(id, workspace_user_id) AS (
                    SELECT folder.id, folder.idUser
                    FROM auth_resource_relation resource_relation
                    INNER JOIN auth_resource_type resource_type ON resource_type.id = resource_relation.idResourceType
                    INNER JOIN file_workspaceFolder folder ON folder.id = resource_relation.resourceId
                    WHERE resource_type.key = 'personal.folder'
                        AND resource_type.active = 1
                        AND resource_relation.scope = 'PERSONAL'
                        AND resource_relation.idTenant IS NULL
                        AND resource_relation.relationKey = 'READ'
                        AND resource_relation.active = 1
                        AND folder.idUser IS NOT NULL
                        AND folder.idTenant IS NULL
                        AND folder.state = 'ACTIVE'
                        AND (
                            resource_relation.idUser = ?
                            OR resource_relation.idGroup IN (SELECT id FROM eligible_group)
                        )
                ),
                shared_tree(id, workspace_user_id) AS (
                    SELECT id, workspace_user_id FROM shared_root
                    UNION
                    SELECT child.id, parent.workspace_user_id
                    FROM file_workspaceFolder child
                    INNER JOIN shared_tree parent ON parent.id = child.idParentFolder
                    WHERE child.idUser = parent.workspace_user_id
                        AND child.idTenant IS NULL
                        AND child.state = 'ACTIVE'
                )
                SELECT folder.*
                FROM file_workspaceFolder folder
                WHERE folder.idTenant IS NULL
                    AND folder.state = 'ACTIVE'
                    AND (folder.idUser = ? OR folder.id IN (SELECT id FROM shared_tree))
                ORDER BY folder.idParentFolder, folder.displayName, folder.id
                LIMIT ? OFFSET ?
                SQL,
            [$principal->id, $principal->id, $principal->id, $perPage, $offset],
        );
    }
}
