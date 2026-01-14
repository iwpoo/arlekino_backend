# Arlekino Backend

A high-performance social commerce service platform built on Laravel 12. The system architecture is engineered to ensure seamless interaction between the social networking modules and the intelligent marketplace.

## 🏗️ Project Overview

The Arlekino project is an innovative hybrid platform that blends social networking and marketplace functionality with deep AI integration. It is positioned not merely as a trading floor, but as an intelligent ecosystem ('AI Market') that automates the interaction between buyers and sellers.

### Key Features

- **Multi-role User System**: Clients, Sellers, and Couriers with role-based access control
- **Social Networking**: Posts, stories, comments, likes, and following system
- **Advanced Product Catalog**: Categories, variants, promotions, and search functionality
- **Order Management**: Complete order lifecycle with QR code tracking
- **Return Processing**: Sophisticated return workflow with logistics integration
- **Real-time Communication**: WebSocket-powered messaging and notifications
- **Payment Integration**: Stripe payments with multi-currency support
- **Search Engine**: Elasticsearch-powered product and content search
- **Analytics Dashboard**: Seller analytics and performance metrics

## 🛠️ Tech Stack

### Core Technologies
- **PHP**: 8.2+
- **Laravel**: 12
- **Database**: MySQL 8.0+
- **Cache**: Redis
- **Search**: Elasticsearch 9.2.3
- **Queue**: Redis (database driver)
- **WebSocket**: Laravel Reverb

### Key Dependencies
- **Authentication**: Laravel Sanctum for API token management
- **Media Processing**: Intervention Image, Laravel FFmpeg
- **SMS/Communication**: Twilio SDK
- **Excel**: Maatwebsite Excel for exports
- **Search**: TNTSearch, Elasticsearch PHP client
- **QR Codes**: chillerlan/php-qrcode

## 🚀 Arlekino Backend: Full Deployment Guide

### 🛠️ Installation & Setup

#### 1. Prerequisites
   Ensure you have Docker and Composer installed.

#### 2. Environment Configuration

```bash
git clone <repository-url>
cd arlekino_backend
composer install
cp .env.example .env
```

**Важно:** В .env установите следующие драйверы для работы с Docker:

```bash
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis

# WebSocket (Reverb)
REVERB_APP_ID=my-app-id
REVERB_APP_KEY=my-app-key
REVERB_APP_SECRET=my-app-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
```

#### 3. Start Infrastructure (Sail)

```bash
./vendor/bin/sail up -d --build
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

#### 4. Running console commands

```bash
./vendor/bin/sail artisan import:market-taxonomy
./vendor/bin/sail artisan currency:refresh
./vendor/bin/sail artisan app:setup-elasticsearch
```

## 🏗️ Queue Management (Background Workers)

The Arlekino system uses priority queue division to ensure highload stability.

#### Running workers in Docker

#### 1. High Priority Queue (Chats, SMS, Orders)

```bash
./vendor/bin/sail artisan queue:work --queue=high --backoff=3 --tries=3 --timeout=30
```

#### 2. Standard Queue (Notifications, Content)

```bash
./vendor/bin/sail artisan queue:work --queue=notifications,default --backoff=5 --tries=2
```

#### 3. Low Priority and Analytics (Video, Reports)

```bash
./vendor/bin/sail artisan queue:work --queue=notifications,default --backoff=5 --tries=2
```

#### Monitoring (Laravel Horizon)

The project is configured to use Horizon for visual queue control:
- Go to address: http://localhost:8000/horizon
- Launching in Sail:

```bash
./vendor/bin/sail artisan horizon
```

## 📡 Real-time & WebSockets (Laravel Reverb)
To operate chats and instant protocols, the Reverb server is used, forwarded through port 8080.

#### Launching the broadcast server:
```bash
./vendor/bin/sail artisan reverb:start --debug
```
