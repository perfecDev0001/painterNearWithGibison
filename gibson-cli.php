<?php
/**
 * Gibson AI Database CLI Tool
 * View and manage Gibson AI database from command line
 */

// Prevent web access
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line.');
}

require_once 'bootstrap.php';
require_once 'core/GibsonAIService.php';

// CLI Colors
class Colors {
    public static $HEADER = "\033[95m";
    public static $BLUE = "\033[94m";
    public static $GREEN = "\033[92m";
    public static $WARNING = "\033[93m";
    public static $FAIL = "\033[91m";
    public static $ENDC = "\033[0m";
    public static $BOLD = "\033[1m";
    public static $UNDERLINE = "\033[4m";
}

function colorize($text, $color) {
    return $color . $text . Colors::$ENDC;
}

function printHeader($text) {
    echo "\n" . colorize("🤖 $text", Colors::$HEADER . Colors::$BOLD) . "\n";
    echo str_repeat("=", strlen($text) + 3) . "\n\n";
}

function printSection($text) {
    echo colorize("📊 $text", Colors::$BLUE . Colors::$BOLD) . "\n";
    echo str_repeat("-", strlen($text) + 3) . "\n";
}

function printSuccess($text) {
    echo colorize("✅ $text", Colors::$GREEN) . "\n";
}

function printError($text) {
    echo colorize("❌ $text", Colors::$FAIL) . "\n";
}

function printWarning($text) {
    echo colorize("⚠️  $text", Colors::$WARNING) . "\n";
}

// Main CLI interface
$command = $argv[1] ?? 'help';

printHeader("Gibson AI Database CLI Tool");

try {
    $gibson = new GibsonAIService();
    printSuccess("Connected to Gibson AI successfully");
    
    switch ($command) {
        case 'leads':
        case 'l':
            printSection("Job Leads");
            $result = $gibson->makeApiCallPublic('/v1/-/job-lead', null, 'GET');
            
            if ($result['success']) {
                $leads = $result['data'];
                printSuccess("Found " . count($leads) . " records");
                echo str_repeat("=", 80) . "\n\n";
                
                if (empty($leads)) {
                    echo "No leads found in database.\n";
                } else {
                    foreach ($leads as $i => $lead) {
                        $isYourLead = strpos($lead['customer_email'] ?? '', 'hamkaloyaroslav@gmail.com') !== false;
                        $isRecent = strtotime($lead['date_created']) > strtotime('-1 hour');
                        
                        echo colorize("--- Lead " . ($i + 1) . " ---", Colors::$BOLD);
                        if ($isYourLead) echo colorize(" [YOUR SUBMISSION]", Colors::$GREEN);
                        if ($isRecent) echo colorize(" [NEW]", Colors::$WARNING);
                        echo "\n\n";
                        
                        printf("%-20s: %s\n", "ID", $lead['id'] ?? 'N/A');
                        printf("%-20s: %s\n", "Customer Name", $lead['customer_name'] ?? 'N/A');
                        printf("%-20s: %s\n", "Email", colorize($lead['customer_email'] ?? 'N/A', Colors::$BLUE));
                        printf("%-20s: %s\n", "Phone", $lead['customer_phone'] ?? 'N/A');
                        printf("%-20s: %s\n", "Description", substr($lead['description'] ?? 'N/A', 0, 60) . (strlen($lead['description'] ?? '') > 60 ? '...' : ''));
                        printf("%-20s: %s\n", "Budget", colorize("£" . number_format($lead['budget'] ?? 0), Colors::$GREEN));
                        printf("%-20s: %s\n", "Status ID", $lead['status_id'] ?? 'N/A');
                        printf("%-20s: %s\n", "Max Claims", $lead['max_claims'] ?? 'N/A');
                        printf("%-20s: %s\n", "Start Date", $lead['preferred_start_date'] ?? 'N/A');
                        printf("%-20s: %s\n", "Created", colorize($lead['date_created'] ?? 'N/A', Colors::$WARNING));
                        printf("%-20s: %s\n", "UUID", $lead['uuid'] ?? 'N/A');
                        echo "\n";
                    }
                }
                
                // Export option
                echo "\n💾 Export full data to JSON? (y/n): ";
                $handle = fopen("php://stdin", "r");
                $export = trim(fgets($handle));
                fclose($handle);
                
                if (strtolower($export) === 'y') {
                    $filename = "gibson_export_leads_" . date('Y-m-d_H-i-s') . ".json";
                    file_put_contents($filename, json_encode($leads, JSON_PRETTY_PRINT));
                    printSuccess("Data exported to: $filename");
                }
                
            } else {
                printError("Failed to retrieve leads: " . ($result['error'] ?? 'Unknown error'));
            }
            break;
            
        case 'statuses':
        case 's':
            printSection("Lead Statuses");
            $result = $gibson->makeApiCallPublic('/v1/-/job-lead-status', null, 'GET');
            
            if ($result['success']) {
                $statuses = $result['data'];
                printSuccess("Found " . count($statuses) . " status records");
                echo str_repeat("=", 50) . "\n\n";
                
                foreach ($statuses as $status) {
                    printf("%-5s %-15s %s\n", 
                        colorize($status['id'], Colors::$BOLD),
                        colorize($status['status_name'], Colors::$GREEN),
                        $status['description'] ?? 'No description'
                    );
                }
            } else {
                printError("Failed to retrieve statuses: " . ($result['error'] ?? 'Unknown error'));
            }
            break;
            
        case 'users':
        case 'u':
            printSection("Users");
            $result = $gibson->makeApiCallPublic('/v1/-/user', null, 'GET');
            
            if ($result['success']) {
                $users = $result['data'];
                printSuccess("Found " . count($users) . " user records");
                echo str_repeat("=", 80) . "\n\n";
                
                // Group by role
                $roleGroups = [];
                foreach ($users as $user) {
                    $roleId = $user['role_id'] ?? 'unknown';
                    $roleGroups[$roleId][] = $user;
                }
                
                $roleNames = [1 => 'Admin', 3 => 'Painter', 4 => 'Customer'];
                
                foreach ($roleGroups as $roleId => $roleUsers) {
                    $roleName = $roleNames[$roleId] ?? "Role $roleId";
                    echo colorize("$roleName Users (" . count($roleUsers) . "):", Colors::$BLUE . Colors::$BOLD) . "\n";
                    
                    foreach (array_slice($roleUsers, 0, 5) as $user) { // Show first 5 of each role
                        $isYourEmail = strpos($user['email'] ?? '', 'hamkaloyaroslav@gmail.com') !== false;
                        
                        printf("  %-4s %-30s %s", 
                            $user['id'],
                            substr($user['name'] ?? 'N/A', 0, 30),
                            $user['email'] ?? 'N/A'
                        );
                        
                        if ($isYourEmail) echo colorize(" [YOU]", Colors::$GREEN);
                        echo "\n";
                    }
                    
                    if (count($roleUsers) > 5) {
                        echo "  ... and " . (count($roleUsers) - 5) . " more\n";
                    }
                    echo "\n";
                }
            } else {
                printError("Failed to retrieve users: " . ($result['error'] ?? 'Unknown error'));
            }
            break;
            
        case 'recent':
        case 'r':
            printSection("Recent Activity (Last 24 Hours)");
            $result = $gibson->makeApiCallPublic('/v1/-/job-lead', null, 'GET');
            
            if ($result['success']) {
                $leads = $result['data'];
                $recentLeads = array_filter($leads, function($lead) {
                    return strtotime($lead['date_created']) > strtotime('-24 hours');
                });
                
                printSuccess("Found " . count($recentLeads) . " recent leads");
                echo str_repeat("=", 60) . "\n\n";
                
                if (empty($recentLeads)) {
                    echo "No recent activity in the last 24 hours.\n";
                } else {
                    foreach ($recentLeads as $lead) {
                        $isYourLead = strpos($lead['customer_email'] ?? '', 'hamkaloyaroslav@gmail.com') !== false;
                        $timeAgo = time() - strtotime($lead['date_created']);
                        $timeAgoText = $timeAgo < 3600 ? floor($timeAgo/60) . 'm ago' : floor($timeAgo/3600) . 'h ago';
                        
                        printf("%-4s %-20s %-30s %s", 
                            colorize($lead['id'], Colors::$BOLD),
                            substr($lead['customer_name'] ?? 'N/A', 0, 20),
                            substr($lead['customer_email'] ?? 'N/A', 0, 30),
                            colorize($timeAgoText, Colors::$WARNING)
                        );
                        
                        if ($isYourLead) echo colorize(" [YOUR LEAD]", Colors::$GREEN);
                        echo "\n";
                    }
                }
            } else {
                printError("Failed to retrieve recent leads: " . ($result['error'] ?? 'Unknown error'));
            }
            break;
            
        case 'analytics':
        case 'a':
            printSection("Analytics");
            
            // Get all data for analytics
            $leadsResult = $gibson->makeApiCallPublic('/v1/-/job-lead', null, 'GET');
            $usersResult = $gibson->makeApiCallPublic('/v1/-/user', null, 'GET');
            
            $leads = $leadsResult['success'] ? $leadsResult['data'] : [];
            $users = $usersResult['success'] ? $usersResult['data'] : [];
            
            // Calculate analytics
            $totalLeads = count($leads);
            $recentLeads = count(array_filter($leads, function($l) { return strtotime($l['date_created']) > strtotime('-24 hours'); }));
            $totalBudget = array_sum(array_column($leads, 'budget'));
            $avgBudget = $totalLeads > 0 ? $totalBudget / $totalLeads : 0;
            
            $painters = array_filter($users, function($u) { return ($u['role_id'] ?? 0) == 3; });
            $customers = array_filter($users, function($u) { return ($u['role_id'] ?? 0) == 4; });
            
            printf("%-25s: %s\n", "Total Leads", colorize($totalLeads, Colors::$GREEN . Colors::$BOLD));
            printf("%-25s: %s\n", "Recent Leads (24h)", colorize($recentLeads, Colors::$WARNING . Colors::$BOLD));
            printf("%-25s: %s\n", "Total Budget Value", colorize("£" . number_format($totalBudget), Colors::$GREEN));
            printf("%-25s: %s\n", "Average Budget", colorize("£" . number_format($avgBudget), Colors::$BLUE));
            printf("%-25s: %s\n", "Total Painters", colorize(count($painters), Colors::$BLUE . Colors::$BOLD));
            printf("%-25s: %s\n", "Total Customers", colorize(count($customers), Colors::$BLUE . Colors::$BOLD));
            printf("%-25s: %s\n", "Total Users", colorize(count($users), Colors::$HEADER . Colors::$BOLD));
            
            // Export analytics
            echo "\n💾 Export full data to JSON? (y/n): ";
            $handle = fopen("php://stdin", "r");
            $export = trim(fgets($handle));
            fclose($handle);
            
            if (strtolower($export) === 'y') {
                $analytics = [
                    'total_leads' => $totalLeads,
                    'total_painters' => count($painters),
                    'total_bids' => 0, // Not implemented yet
                    'assigned_leads' => 0 // Not implemented yet
                ];
                $filename = "gibson_export_analytics_" . date('Y-m-d_H-i-s') . ".json";
                file_put_contents($filename, json_encode($analytics, JSON_PRETTY_PRINT));
                printSuccess("Data exported to: $filename");
            }
            break;
            
        case 'watch':
        case 'w':
            printSection("Live Watch Mode");
            echo "Watching for new leads... (Press Ctrl+C to stop)\n\n";
            
            $lastCount = 0;
            while (true) {
                $result = $gibson->makeApiCallPublic('/v1/-/job-lead', null, 'GET');
                if ($result['success']) {
                    $currentCount = count($result['data']);
                    if ($currentCount > $lastCount) {
                        $newLeads = $currentCount - $lastCount;
                        echo colorize("[" . date('H:i:s') . "] 🎉 $newLeads new lead(s) detected! Total: $currentCount", Colors::$GREEN . Colors::$BOLD) . "\n";
                        
                        // Show the latest lead
                        $latestLead = end($result['data']);
                        if ($latestLead) {
                            echo "  Latest: " . ($latestLead['customer_name'] ?? 'N/A') . " - " . ($latestLead['customer_email'] ?? 'N/A') . "\n";
                        }
                    } else {
                        echo colorize("[" . date('H:i:s') . "] No new leads. Total: $currentCount", Colors::$BLUE) . "\r";
                    }
                    $lastCount = $currentCount;
                }
                sleep(5); // Check every 5 seconds
            }
            break;
            
        case 'help':
        case 'h':
        default:
            echo colorize("Available Commands:", Colors::$BOLD) . "\n\n";
            echo colorize("leads, l", Colors::$GREEN) . "      - View all job leads\n";
            echo colorize("statuses, s", Colors::$GREEN) . "   - View lead statuses\n";
            echo colorize("users, u", Colors::$GREEN) . "      - View all users\n";
            echo colorize("recent, r", Colors::$GREEN) . "     - View recent activity (24h)\n";
            echo colorize("analytics, a", Colors::$GREEN) . "  - View database analytics\n";
            echo colorize("watch, w", Colors::$GREEN) . "      - Live watch for new leads\n";
            echo colorize("help, h", Colors::$GREEN) . "       - Show this help\n\n";
            
            echo colorize("Examples:", Colors::$BOLD) . "\n";
            echo "php gibson-cli.php leads     # View all leads\n";
            echo "php gibson-cli.php recent    # View recent activity\n";
            echo "php gibson-cli.php watch     # Live monitoring\n";
            break;
    }
    
} catch (Exception $e) {
    printError("Error: " . $e->getMessage());
}

echo "\n" . colorize("🏁 Done!", Colors::$BOLD) . "\n";
?>