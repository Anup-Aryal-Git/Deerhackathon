<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$auth = new Auth();
$db = new Database();
$conn = $db->getConnection();

if ($method === 'GET') {
    $payload = $auth->requireAuth();
    $userId = $payload['userId'];
    
    // Get all feed items: camps, tasks, campaigns, and polls
    $feedItems = [];
    
    // 1. Blood Camps (upcoming)
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            'camp' as type,
            c.title,
            c.description,
            c.date_time,
            c.location_address as location,
            c.status,
            o.name as org_name,
            o.verified as org_verified,
            o.contact as org_contact,
            o.created_by_user_id as org_owner_id,
            COUNT(DISTINCT ca.id) as attendee_count,
            c.created_at,
            (SELECT COUNT(*) FROM camp_attendees WHERE camp_id = c.id AND user_id = ?) as user_joined
        FROM camps c
        LEFT JOIN organizations o ON c.org_id = o.id
        LEFT JOIN camp_attendees ca ON c.id = ca.camp_id
        WHERE c.status IN ('upcoming', 'ongoing')
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $camps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($camps as $camp) {
        // Likes
        $likeStmt = $conn->prepare("
            SELECT COUNT(*) as cnt 
            FROM post_likes 
            WHERE post_type = 'camp' AND post_id = ?
        ");
        $likeStmt->execute([$camp['id']]);
        $likeRow = $likeStmt->fetch(PDO::FETCH_ASSOC);

        $userLikeStmt = $conn->prepare("
            SELECT 1 FROM post_likes 
            WHERE post_type = 'camp' AND post_id = ? AND user_id = ? 
            LIMIT 1
        ");
        $userLikeStmt->execute([$camp['id'], $userId]);
        $userLiked = (bool)$userLikeStmt->fetchColumn();

        $interestStmt = $conn->prepare("
            SELECT COUNT(*) as cnt 
            FROM post_interests 
            WHERE post_type = 'camp' AND post_id = ?
        ");
        $interestStmt->execute([$camp['id']]);
        $interestRow = $interestStmt->fetch(PDO::FETCH_ASSOC);

        $userInterestStmt = $conn->prepare("
            SELECT 1 FROM post_interests 
            WHERE post_type = 'camp' AND post_id = ? AND user_id = ? 
            LIMIT 1
        ");
        $userInterestStmt->execute([$camp['id'], $userId]);
        $userInterested = (bool)$userInterestStmt->fetchColumn();

        $feedItems[] = [
            'id' => $camp['id'],
            'type' => 'camp',
            'title' => $camp['title'],
            'description' => $camp['description'],
            'dateTime' => $camp['date_time'],
            'location' => $camp['location'],
            'status' => $camp['status'],
            'orgName' => $camp['org_name'],
            'orgVerified' => (bool)$camp['org_verified'],
            'attendeeCount' => (int)$camp['attendee_count'],
            'userJoined' => (bool)$camp['user_joined'],
            'createdAt' => $camp['created_at'],
            'contact' => $camp['org_contact'],
            'ownerId' => (int)$camp['org_owner_id'],
            'likeCount' => (int)($likeRow['cnt'] ?? 0),
            'userLiked' => $userLiked,
            'interestCount' => (int)($interestRow['cnt'] ?? 0),
            'userInterested' => $userInterested
        ];
    }
    
    // 2. Volunteer Tasks (open)
    $stmt = $conn->prepare("
        SELECT 
            t.id,
            'task' as type,
            t.title,
            t.description,
            t.date_time,
            t.location,
            t.hours_estimated,
            t.status,
            o.name as org_name,
            o.verified as org_verified,
            o.contact as org_contact,
            o.created_by_user_id as org_owner_id,
            COUNT(DISTINCT ta.id) as applicant_count,
            t.created_at,
            (SELECT COUNT(*) FROM task_applicants WHERE task_id = t.id AND user_id = ?) as user_applied
        FROM volunteer_tasks t
        LEFT JOIN organizations o ON t.org_id = o.id
        LEFT JOIN task_applicants ta ON t.id = ta.task_id
        WHERE t.status IN ('open', 'in_progress')
        GROUP BY t.id
        ORDER BY t.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tasks as $task) {
        // Likes
        $likeStmt = $conn->prepare("
            SELECT COUNT(*) as cnt 
            FROM post_likes 
            WHERE post_type = 'task' AND post_id = ?
        ");
        $likeStmt->execute([$task['id']]);
        $likeRow = $likeStmt->fetch(PDO::FETCH_ASSOC);

        $userLikeStmt = $conn->prepare("
            SELECT 1 FROM post_likes 
            WHERE post_type = 'task' AND post_id = ? AND user_id = ? 
            LIMIT 1
        ");
        $userLikeStmt->execute([$task['id'], $userId]);
        $userLiked = (bool)$userLikeStmt->fetchColumn();

        $interestStmt = $conn->prepare("
            SELECT COUNT(*) as cnt 
            FROM post_interests 
            WHERE post_type = 'task' AND post_id = ?
        ");
        $interestStmt->execute([$task['id']]);
        $interestRow = $interestStmt->fetch(PDO::FETCH_ASSOC);

        $userInterestStmt = $conn->prepare("
            SELECT 1 FROM post_interests 
            WHERE post_type = 'task' AND post_id = ? AND user_id = ? 
            LIMIT 1
        ");
        $userInterestStmt->execute([$task['id'], $userId]);
        $userInterested = (bool)$userInterestStmt->fetchColumn();

        $feedItems[] = [
            'id' => $task['id'],
            'type' => 'task',
            'title' => $task['title'],
            'description' => $task['description'],
            'dateTime' => $task['date_time'],
            'location' => $task['location'],
            'hoursEstimated' => (int)$task['hours_estimated'],
            'status' => $task['status'],
            'orgName' => $task['org_name'],
            'orgVerified' => (bool)$task['org_verified'],
            'applicantCount' => (int)$task['applicant_count'],
            'userApplied' => (bool)$task['user_applied'],
            'createdAt' => $task['created_at'],
            'contact' => $task['org_contact'],
            'ownerId' => (int)$task['org_owner_id'],
            'likeCount' => (int)($likeRow['cnt'] ?? 0),
            'userLiked' => $userLiked,
            'interestCount' => (int)($interestRow['cnt'] ?? 0),
            'userInterested' => $userInterested
        ];
    }
    
    // 3. Social Campaigns (active)
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            'campaign' as type,
            c.title,
            c.description,
            c.goal_tokens,
            c.raised_tokens,
            c.status,
            o.name as org_name,
            o.verified as org_verified,
            o.contact as org_contact,
            o.created_by_user_id as org_owner_id,
            COUNT(DISTINCT cs.id) as supporter_count,
            c.created_at,
            (SELECT COUNT(*) FROM campaign_supporters WHERE campaign_id = c.id AND user_id = ?) as user_supported
        FROM campaigns c
        LEFT JOIN organizations o ON c.org_id = o.id
        LEFT JOIN campaign_supporters cs ON c.id = cs.campaign_id
        WHERE c.status = 'active'
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($campaigns as $campaign) {
        // Likes
        $likeStmt = $conn->prepare("
            SELECT COUNT(*) as cnt 
            FROM post_likes 
            WHERE post_type = 'campaign' AND post_id = ?
        ");
        $likeStmt->execute([$campaign['id']]);
        $likeRow = $likeStmt->fetch(PDO::FETCH_ASSOC);

        $userLikeStmt = $conn->prepare("
            SELECT 1 FROM post_likes 
            WHERE post_type = 'campaign' AND post_id = ? AND user_id = ? 
            LIMIT 1
        ");
        $userLikeStmt->execute([$campaign['id'], $userId]);
        $userLiked = (bool)$userLikeStmt->fetchColumn();

        $interestStmt = $conn->prepare("
            SELECT COUNT(*) as cnt 
            FROM post_interests 
            WHERE post_type = 'campaign' AND post_id = ?
        ");
        $interestStmt->execute([$campaign['id']]);
        $interestRow = $interestStmt->fetch(PDO::FETCH_ASSOC);

        $userInterestStmt = $conn->prepare("
            SELECT 1 FROM post_interests 
            WHERE post_type = 'campaign' AND post_id = ? AND user_id = ? 
            LIMIT 1
        ");
        $userInterestStmt->execute([$campaign['id'], $userId]);
        $userInterested = (bool)$userInterestStmt->fetchColumn();

        $feedItems[] = [
            'id' => $campaign['id'],
            'type' => 'campaign',
            'title' => $campaign['title'],
            'description' => $campaign['description'],
            'goalTokens' => (int)$campaign['goal_tokens'],
            'raisedTokens' => (int)$campaign['raised_tokens'],
            'status' => $campaign['status'],
            'orgName' => $campaign['org_name'],
            'orgVerified' => (bool)$campaign['org_verified'],
            'supporterCount' => (int)$campaign['supporter_count'],
            'userSupported' => (bool)$campaign['user_supported'],
            'createdAt' => $campaign['created_at'],
            'contact' => $campaign['org_contact'],
            'ownerId' => (int)$campaign['org_owner_id'],
            'likeCount' => (int)($likeRow['cnt'] ?? 0),
            'userLiked' => $userLiked,
            'interestCount' => (int)($interestRow['cnt'] ?? 0),
            'userInterested' => $userInterested
        ];
    }
    
    // 4. Polls (active)
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            'poll' as type,
            p.question,
            p.options,
            p.status,
            p.expires_at,
            u.name as creator_name,
            u.role as creator_role,
            p.created_by,
            o.name as org_name,
            o.verified as org_verified,
            o.contact as org_contact,
            o.created_by_user_id as org_owner_id,
            COUNT(DISTINCT pv.id) as vote_count,
            p.created_at,
            (SELECT option_id FROM poll_votes WHERE poll_id = p.id AND user_id = ?) as user_vote
        FROM polls p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN organizations o ON o.created_by_user_id = u.id
        LEFT JOIN poll_votes pv ON p.id = pv.poll_id
        WHERE p.status = 'active' AND (p.expires_at IS NULL OR p.expires_at > NOW())
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($polls as $poll) {
        $options = json_decode($poll['options'], true);
        // Count votes for each option
        $voteStmt = $conn->prepare("
            SELECT option_id, COUNT(*) as count 
            FROM poll_votes 
            WHERE poll_id = ? 
            GROUP BY option_id
        ");
        $voteStmt->execute([$poll['id']]);
        $votes = $voteStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $voteCounts = [];
        foreach ($votes as $vote) {
            $voteCounts[$vote['option_id']] = (int)$vote['count'];
        }
        
        // Update option counts
        foreach ($options as &$option) {
            $option['count'] = $voteCounts[$option['id']] ?? 0;
        }
        
        // Likes
        $likeStmt = $conn->prepare("
            SELECT COUNT(*) as cnt 
            FROM post_likes 
            WHERE post_type = 'poll' AND post_id = ?
        ");
        $likeStmt->execute([$poll['id']]);
        $likeRow = $likeStmt->fetch(PDO::FETCH_ASSOC);

        $userLikeStmt = $conn->prepare("
            SELECT 1 FROM post_likes 
            WHERE post_type = 'poll' AND post_id = ? AND user_id = ? 
            LIMIT 1
        ");
        $userLikeStmt->execute([$poll['id'], $userId]);
        $userLiked = (bool)$userLikeStmt->fetchColumn();

        $interestStmt = $conn->prepare("
            SELECT COUNT(*) as cnt 
            FROM post_interests 
            WHERE post_type = 'poll' AND post_id = ?
        ");
        $interestStmt->execute([$poll['id']]);
        $interestRow = $interestStmt->fetch(PDO::FETCH_ASSOC);

        $userInterestStmt = $conn->prepare("
            SELECT 1 FROM post_interests 
            WHERE post_type = 'poll' AND post_id = ? AND user_id = ? 
            LIMIT 1
        ");
        $userInterestStmt->execute([$poll['id'], $userId]);
        $userInterested = (bool)$userInterestStmt->fetchColumn();

        $feedItems[] = [
            'id' => $poll['id'],
            'type' => 'poll',
            'question' => $poll['question'],
            'options' => $options,
            'status' => $poll['status'],
            'expiresAt' => $poll['expires_at'],
            'creatorName' => $poll['creator_name'],
            'creatorRole' => $poll['creator_role'],
            'orgName' => $poll['org_name'], // Organization name if created by organization
            'orgVerified' => (bool)$poll['org_verified'],
            'voteCount' => (int)$poll['vote_count'],
            'userVote' => $poll['user_vote'],
            'createdAt' => $poll['created_at'],
            'contact' => $poll['org_contact'],
            'ownerId' => (int)$poll['created_by'],
            'orgId' => $poll['org_owner_id'] ? (int)$poll['org_owner_id'] : null,
            'likeCount' => (int)($likeRow['cnt'] ?? 0),
            'userLiked' => $userLiked,
            'interestCount' => (int)($interestRow['cnt'] ?? 0),
            'userInterested' => $userInterested
        ];
    }
    
    // Sort by created_at descending (newest first)
    usort($feedItems, function($a, $b) {
        return strtotime($b['createdAt']) - strtotime($a['createdAt']);
    });
    
    http_response_code(200);
    echo json_encode($feedItems);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>



