<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMarketingChatbotLeadsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('marketing_chatbot_leads')) {
            Schema::create('marketing_chatbot_leads', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 120);
                $table->string('email', 120)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('company_name', 160);
                $table->string('source', 60)->default('marketing_chatbot');
                $table->string('status', 30)->default('new');
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('marketing_chatbot_leads');
    }
}
