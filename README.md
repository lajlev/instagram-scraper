# Instagram Scraper

A lightweight, self-hosted system that scrapes the latest posts from a single Instagram profile once daily and displays them on a WordPress site. Replaces paid services like Apify with a near-zero-cost solution on Google Cloud Run.

## Architecture

```
Cloud Scheduler (cron: 0 0 * * *)
  └── Cloud Run Job
        └── Python script (Instaloader)
              └── Writes JSON to Cloud Storage bucket (public read)
                    └── WordPress fetches JSON via wp_remote_get()
                          └── Downloads images to media library
                                └── Displays via [instagram_feed] shortcode
```

## Components

### 1. Scraper (`scraper/`)

A Python script that uses [Instaloader](https://instaloader.github.io/) to fetch the latest posts from an Instagram profile and writes them as `posts.json` to a Google Cloud Storage bucket.

**Environment variables:**

| Variable | Description | Required | Default |
|---|---|---|---|
| `INSTAGRAM_USERNAME` | Target Instagram profile | Yes | — |
| `GCS_BUCKET` | Cloud Storage bucket name | Yes | — |
| `POST_COUNT` | Number of posts to fetch | No | `12` |
| `IG_SESSION_ID` | Session cookie for private profiles | No | — |

### 2. WordPress Plugin (`wordpress/`)

A WordPress plugin that fetches the JSON feed, downloads images to the media library, and displays them in a responsive grid.

**Shortcode:** `[instagram_feed]`

**Attributes:**

| Attribute | Description | Default |
|---|---|---|
| `columns` | Grid columns (1-4) | `3` |
| `size` | Image size (thumbnail, medium, large, full) | `medium` |
| `count` | Number of posts to show | `12` |

### 3. Infrastructure (`infra/`)

A deployment script that sets up all GCP resources with a single command.

## Setup

### Prerequisites

- Google Cloud account with a project
- `gcloud` CLI installed and authenticated
- WordPress site with admin access

### Deploy the Scraper

```bash
# Deploy everything to GCP
./infra/deploy.sh <project-id> <instagram-username> [region]

# Example
./infra/deploy.sh my-project myclient europe-west1
```

This creates:
- A GCS bucket with public read access
- A Docker image in Artifact Registry
- A Cloud Run Job
- A Cloud Scheduler job (runs daily at midnight Copenhagen time)

### Run Manually

```bash
gcloud run jobs execute instagram-scraper --region=europe-west1 --project=my-project
```

### Install the WordPress Plugin

1. Copy the `wordpress/` folder to `wp-content/plugins/instagram-scraper/`
2. Activate the plugin in WordPress admin
3. Go to **Settings > Instagram Feed**
4. Enter your JSON feed URL: `https://storage.googleapis.com/<bucket>/posts.json`
5. Add `[instagram_feed]` to any page or post

## Cost

Everything fits within Google Cloud's free tier:

| Resource | Usage/month | Free tier | Cost |
|---|---|---|---|
| Cloud Run Job | ~30 invocations | 240k vCPU-sec | $0.00 |
| Cloud Scheduler | 1 job | 3 free jobs | $0.00 |
| Cloud Storage | < 1 MB | 5 GB | $0.00 |
| GCS egress | < 100 MB | 1 GB | $0.00 |
| Artifact Registry | ~150 MB | 500 MB | $0.00 |

## JSON Schema

The scraper writes `posts.json` with this structure:

```json
{
  "scraped_at": "2026-02-24T00:00:00Z",
  "profile": "target_username",
  "posts": [
    {
      "id": "shortcode_abc123",
      "permalink": "https://www.instagram.com/p/abc123/",
      "caption": "Post caption text...",
      "image_url": "https://...",
      "thumbnail_url": "https://...",
      "is_video": false,
      "video_url": null,
      "timestamp": "2026-02-23T14:30:00Z",
      "likes": 142,
      "comments": 8,
      "hashtags": ["#example", "#photo"]
    }
  ]
}
```

## License

GPL v2 or later
