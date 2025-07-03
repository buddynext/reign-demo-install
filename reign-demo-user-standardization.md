# Reign Demo User Standardization Strategy

## Overview
Standardize all demo users across 40 Reign demos with consistent IDs, professional names, and branded emails for a polished demo experience.

## Standardized User Structure

### Core Demo Users (Used in All Demos)

#### 1. Admin User
```
ID: 101
Username: reign_admin
Email: admin@reigndemo.com
Display Name: Alex Thompson
Role: Administrator
Bio: "Site administrator and community manager"
Avatar: Professional headshot
```

#### 2. Editor/Moderator
```
ID: 102
Username: reign_editor
Email: editor@reigndemo.com
Display Name: Sarah Mitchell
Role: Editor
Bio: "Content editor and community moderator"
Avatar: Professional headshot
```

#### 3. Shop Manager (for eCommerce demos)
```
ID: 103
Username: reign_shop
Email: shop@reigndemo.com
Display Name: Michael Chen
Role: Shop Manager
Bio: "E-commerce manager and vendor coordinator"
Avatar: Professional headshot
```

#### 4. Instructor (for LMS demos)
```
ID: 104
Username: reign_instructor
Email: instructor@reigndemo.com
Display Name: Dr. Emily Roberts
Role: Instructor/Teacher
Bio: "Senior instructor and course creator"
Avatar: Professional headshot
```

#### 5. Community Members (Standard Set)
```
ID: 105-120 (15 standard members)
Pattern: member_{number}@reigndemo.com
```

### Standard Community Members List

```
ID: 105 | james_wilson     | james@reigndemo.com     | James Wilson
ID: 106 | emma_davis       | emma@reigndemo.com      | Emma Davis
ID: 107 | robert_jones     | robert@reigndemo.com    | Robert Jones
ID: 108 | sophia_brown     | sophia@reigndemo.com    | Sophia Brown
ID: 109 | william_garcia   | william@reigndemo.com   | William Garcia
ID: 110 | olivia_miller    | olivia@reigndemo.com    | Olivia Miller
ID: 111 | david_martinez   | david@reigndemo.com     | David Martinez
ID: 112 | isabella_taylor  | isabella@reigndemo.com  | Isabella Taylor
ID: 113 | joseph_anderson  | joseph@reigndemo.com    | Joseph Anderson
ID: 114 | mia_thomas       | mia@reigndemo.com       | Mia Thomas
ID: 115 | charles_jackson  | charles@reigndemo.com   | Charles Jackson
ID: 116 | charlotte_white  | charlotte@reigndemo.com | Charlotte White
ID: 117 | daniel_harris    | daniel@reigndemo.com    | Daniel Harris
ID: 118 | amelia_clark     | amelia@reigndemo.com    | Amelia Clark
ID: 119 | matthew_lewis    | matthew@reigndemo.com   | Matthew Lewis
ID: 120 | harper_walker    | harper@reigndemo.com    | Harper Walker
```

### Demo-Specific Users (121-150)

#### Dating Demo Users
```
ID: 121 | jessica_love     | jessica@reigndating.com    | Jessica Love | Seeking Partner
ID: 122 | ryan_match       | ryan@reigndating.com       | Ryan Match   | Seeking Partner
ID: 123 | ashley_heart     | ashley@reigndating.com     | Ashley Heart | Seeking Friend
```

#### Job Board Demo Users
```
ID: 124 | hire_manager     | hiring@reignjobs.com       | Jennifer HR     | Recruiter
ID: 125 | job_seeker1      | candidate1@reignjobs.com   | Mark Developer  | Job Seeker
ID: 126 | job_seeker2      | candidate2@reignjobs.com   | Lisa Designer   | Job Seeker
```

#### Marketplace Vendors
```
ID: 127 | vendor_fashion   | fashion@reignmarket.com    | Fashion Boutique | Vendor
ID: 128 | vendor_tech      | tech@reignmarket.com       | Tech Store       | Vendor
ID: 129 | vendor_crafts    | crafts@reignmarket.com     | Handmade Crafts  | Vendor
```

## User Profile Standardization

### Profile Fields Template
```php
$standard_profile_data = array(
    'avatar' => 'https://installer.wbcomdesigns.com/reign-demos/avatars/user-{ID}.jpg',
    'cover_image' => 'https://installer.wbcomdesigns.com/reign-demos/covers/cover-{ID}.jpg',
    'bio' => 'Generated bio based on role and demo type',
    'location' => 'Various cities for diversity',
    'website' => 'https://reigndemo.com/users/{username}',
    'social_links' => array(
        'twitter' => '@{username}_reign',
        'linkedin' => 'linkedin.com/in/{username}-reign'
    )
);
```

### BuddyPress Profile Fields
```
Name: [Display Name]
About: [Role-specific bio]
Location: [City, Country]
Interests: [Demo-relevant interests]
Skills: [Demo-relevant skills]
```

## Implementation Script

### One-Time User Standardization Script
```php
/**
 * Run this script once on each demo site to standardize users
 */
class Reign_Demo_User_Standardizer {
    
    private $standard_users = array(
        101 => array(
            'user_login' => 'reign_admin',
            'user_email' => 'admin@reigndemo.com',
            'display_name' => 'Alex Thompson',
            'role' => 'administrator',
            'bio' => 'Site administrator and community manager'
        ),
        102 => array(
            'user_login' => 'reign_editor',
            'user_email' => 'editor@reigndemo.com',
            'display_name' => 'Sarah Mitchell',
            'role' => 'editor',
            'bio' => 'Content editor and community moderator'
        ),
        // ... more users
    );
    
    public function standardize_demo_users() {
        global $wpdb;
        
        // Step 1: Backup existing users
        $this->backup_existing_users();
        
        // Step 2: Clear existing users (except ID 1)
        $wpdb->query("DELETE FROM {$wpdb->users} WHERE ID > 1");
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE user_id > 1");
        
        // Step 3: Insert standardized users
        foreach ($this->standard_users as $new_id => $user_data) {
            $this->create_standard_user($new_id, $user_data);
        }
        
        // Step 4: Update all content to use new user IDs
        $this->update_content_authors();
        
        // Step 5: Set up BuddyPress profiles
        if (function_exists('buddypress')) {
            $this->setup_buddypress_profiles();
        }
        
        // Step 6: Create demo-specific content
        $this->create_demo_specific_content();
    }
    
    private function create_standard_user($user_id, $data) {
        global $wpdb;
        
        // Insert user with specific ID
        $wpdb->insert(
            $wpdb->users,
            array(
                'ID' => $user_id,
                'user_login' => $data['user_login'],
                'user_pass' => wp_hash_password('demo123!@#'),
                'user_email' => $data['user_email'],
                'user_nicename' => sanitize_title($data['user_login']),
                'display_name' => $data['display_name'],
                'user_registered' => current_time('mysql')
            )
        );
        
        // Set role
        $user = new WP_User($user_id);
        $user->set_role($data['role']);
        
        // Add meta data
        update_user_meta($user_id, 'description', $data['bio']);
        update_user_meta($user_id, '_reign_demo_user', true);
        
        // Add avatar
        $avatar_url = "https://installer.wbcomdesigns.com/reign-demos/avatars/{$user_id}.jpg";
        update_user_meta($user_id, 'reign_avatar_url', $avatar_url);
    }
    
    private function update_content_authors() {
        global $wpdb;
        
        // Map old user IDs to new standardized IDs
        $author_mapping = $this->generate_author_mapping();
        
        // Update posts
        foreach ($author_mapping as $old_id => $new_id) {
            $wpdb->update(
                $wpdb->posts,
                array('post_author' => $new_id),
                array('post_author' => $old_id)
            );
        }
        
        // Update comments
        foreach ($author_mapping as $old_id => $new_id) {
            $wpdb->update(
                $wpdb->comments,
                array('user_id' => $new_id),
                array('user_id' => $old_id)
            );
        }
    }
}
```

## User Generation Rules by Demo Type

### Community Demos
- 15-20 active members
- Varied activity levels
- Friend connections between users
- Group memberships

### Education/LMS Demos
- 2-3 instructors (IDs: 104, 130, 131)
- 10-15 students (IDs: 105-120)
- Course enrollments
- Progress tracking

### Marketplace Demos
- 5-8 vendors (IDs: 127-135)
- 10+ customers (IDs: 105-120)
- Order history
- Product reviews

### Job Board Demos
- 3-5 employers (IDs: 124, 136-139)
- 10+ job seekers (IDs: 105-120)
- Applications submitted
- Company profiles

## Avatar and Profile Image Strategy

### Avatar Storage Structure
```
/reign-demos/avatars/
├── 101.jpg (Alex Thompson - Admin)
├── 102.jpg (Sarah Mitchell - Editor)
├── 103.jpg (Michael Chen - Shop Manager)
├── 104.jpg (Dr. Emily Roberts - Instructor)
├── 105.jpg (James Wilson)
├── ... (all standardized avatars)
```

### Cover Image Strategy
```
/reign-demos/covers/
├── cover-101.jpg (Professional office background)
├── cover-102.jpg (Creative workspace)
├── cover-103.jpg (E-commerce themed)
├── cover-104.jpg (Educational setting)
├── ... (themed cover images)
```

## Benefits of Standardization

1. **Consistency**: Same users across all demos
2. **Professional**: Branded emails and realistic names
3. **Memorable**: Users can recognize demo personas
4. **Scalable**: Easy to add new demo-specific users
5. **Maintainable**: One-time setup, permanent solution
6. **Realistic**: Proper role distribution

## Maintenance Guidelines

### Adding New Users
- Always use IDs 151+ for new additions
- Follow naming convention
- Use @reigndemo.com for general users
- Use @reign{demotype}.com for specific demos

### Updating Existing Users
- Maintain ID consistency
- Update across all affected demos
- Document changes

### Profile Content
- Keep bios professional and relevant
- Update based on demo context
- Maintain consistent quality

## Quality Checklist

- [ ] All users have professional display names
- [ ] Email addresses use consistent domain
- [ ] Avatars uploaded for all users
- [ ] Bios are relevant to demo type
- [ ] Friend connections established (BuddyPress)
- [ ] Group memberships assigned
- [ ] User roles properly set
- [ ] Demo-specific users added where needed
- [ ] No duplicate emails or usernames
- [ ] All IDs are 100+