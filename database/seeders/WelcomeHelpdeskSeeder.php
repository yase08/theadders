<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskArticle;

class WelcomeHelpdeskSeeder extends Seeder
{
    public function run(): void
    {
        // Create Welcome category if not exists
        $category = HelpdeskCategory::firstOrCreate(
            ['title' => 'Welcome'],
            [
                'description' => 'Welcome page content',
                'sort_order' => 0,
                'is_active' => true,
            ]
        );

        // Welcome articles data
        $articles = [
            [
                'title' => 'Getting Started',
                'content' => "**How does Adders work?**\nUpload an item → Find something you like → Chat → Swap.\nUsers coordinate exchange time and place directly.\n\n**Is Adders free?**\nYes. If paid features appear later, we'll notify you clearly.",
                'thumbnail' => 'Adders_Helpdesk-07.png',
            ],
            [
                'title' => 'Safe Swapping',
                'content' => "**How do I stay safe?**\n• Meet in public areas\n• Check item's condition on the spot\n• Communicate through the app messaging feature\n• Decline if it feels unsafe\n\n**Issue with another user?**\nReport them via the app. We review, but we don't mediate trades.",
                'thumbnail' => 'Adders_Helpdesk-08.png',
            ],
            [
                'title' => "What You Can / Can't Swap",
                'content' => "**Allowed**\nClothes • Shoes • Home Items\nBaby gear • Gadgets • Books • Toys\nInstruments • Hobby items\n\n**Not Allowed**\nWeapons • Chemical • Medicines • Food • Perishable Items • Animals\nCounterfeit Goods • Adult Content • Legal Documents • Illegal Items.",
                'thumbnail' => 'Adders_Helpdesk-09.png',
            ],
            [
                'title' => 'Fixing Issues',
                'content' => "**App not working?**\n• Check your internet\n• Restart the app\n• Log out & Back in\n• Update Adders to latest version\n\n**Photo upload issues?**\nCheck permissions + file size/format.",
                'thumbnail' => 'Adders_Helpdesk-10.png',
            ],
            [
                'title' => 'Community Rules',
                'content' => "Be honest. Be respectful.\nNo pressure. No scams.\nReport unsafe behavior anytime.",
                'thumbnail' => 'Adders_Helpdesk-11.png',
            ],
            [
                'title' => 'Need Help',
                'content' => "Email us at office@theadders.co\nWe're here to help",
                'thumbnail' => 'Adders_Helpdesk-12.png',
            ],
        ];

        foreach ($articles as $article) {
            HelpdeskArticle::firstOrCreate(
                ['title' => $article['title'], 'category_id' => $category->id],
                [
                    'content' => $article['content'],
                    'thumbnail' => $article['thumbnail'],
                    'type' => 'text',
                    'is_active' => true,
                ]
            );
        }
    }
}
