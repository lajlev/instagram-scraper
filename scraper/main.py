"""
Instagram Profile Scraper

Fetches the latest posts from a single Instagram profile using Instaloader
and writes them as JSON to a Google Cloud Storage bucket.

Environment variables:
    INSTAGRAM_USERNAME  - Target profile to scrape (required)
    GCS_BUCKET          - Cloud Storage bucket name (required)
    POST_COUNT          - Number of posts to fetch (default: 12)
    IG_LOGIN_USER       - Instagram login username (recommended)
    IG_LOGIN_PASS       - Instagram login password (recommended)
    IG_SESSION_ID       - Alternative: session cookie instead of user/pass
"""

import json
import os
import re
import sys
from datetime import datetime, timezone
from urllib.parse import unquote

import instaloader
from google.cloud import storage


def extract_hashtags(caption: str) -> list[str]:
    """Extract hashtags from a caption string."""
    if not caption:
        return []
    return re.findall(r"#\w+", caption)


def scrape_profile(
    username: str,
    post_count: int,
    login_user: str | None = None,
    login_pass: str | None = None,
    session_id: str | None = None,
) -> dict:
    """Scrape the latest posts from an Instagram profile."""
    loader = instaloader.Instaloader(
        download_pictures=False,
        download_videos=False,
        download_video_thumbnails=False,
        download_geotags=False,
        download_comments=False,
        save_metadata=False,
        compress_json=False,
        quiet=True,
        max_connection_attempts=1,
    )

    # Auth method 1: username/password login (preferred — uses different API path)
    if login_user and login_pass:
        print(f"Logging in as: {login_user}")
        try:
            loader.login(login_user, login_pass)
            print(f"Authenticated as: {login_user}")
        except instaloader.TwoFactorAuthRequiredException:
            print("ERROR: Two-factor authentication is enabled on this account.")
            print("Use IG_SESSION_ID instead (get sessionid cookie from browser).")
            sys.exit(1)
        except instaloader.BadCredentialsException:
            print("ERROR: Bad credentials. Check IG_LOGIN_USER and IG_LOGIN_PASS.")
            sys.exit(1)
        except Exception as e:
            print(f"ERROR: Login failed: {e}")
            sys.exit(1)

    # Auth method 2: session cookie (fallback)
    elif session_id:
        session_id = unquote(session_id)
        user_id = session_id.split(":")[0] if ":" in session_id else ""

        loader.context._session.cookies.set(
            "sessionid", session_id, domain=".instagram.com", path="/"
        )
        if user_id:
            loader.context._session.cookies.set(
                "ds_user_id", user_id, domain=".instagram.com", path="/"
            )

        try:
            username_from_session = loader.test_login()
            if username_from_session:
                print(f"Authenticated via session cookie as: {username_from_session}")
            else:
                print("WARNING: Session cookie not recognized. Will try anyway...")
        except Exception as e:
            print(f"WARNING: Could not verify session: {e}. Will try anyway...")

    else:
        print("WARNING: No credentials provided. Running anonymously (may get rate-limited).")

    print(f"Loading profile: {username}")
    profile = instaloader.Profile.from_username(loader.context, username)
    print(f"Profile loaded: {profile.full_name} ({profile.mediacount} posts)")

    posts = []
    for i, post in enumerate(profile.get_posts()):
        if i >= post_count:
            break

        posts.append({
            "id": post.shortcode,
            "permalink": f"https://www.instagram.com/p/{post.shortcode}/",
            "caption": post.caption or "",
            "image_url": post.url,
            "thumbnail_url": post.url,
            "is_video": post.is_video,
            "video_url": post.video_url if post.is_video else None,
            "timestamp": post.date_utc.isoformat() + "Z",
            "likes": post.likes,
            "comments": post.comments,
            "hashtags": extract_hashtags(post.caption),
        })
        print(f"  Fetched post {i + 1}/{post_count}: {post.shortcode}")

    return {
        "scraped_at": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
        "profile": username,
        "posts": posts,
    }


def upload_to_gcs(bucket_name: str, data: dict) -> str:
    """Upload posts.json to a GCS bucket. Returns the public URL."""
    client = storage.Client()
    bucket = client.bucket(bucket_name)
    blob = bucket.blob("posts.json")

    json_content = json.dumps(data, indent=2, ensure_ascii=False)
    blob.upload_from_string(
        json_content,
        content_type="application/json",
    )
    blob.cache_control = "public, max-age=3600"
    blob.patch()

    public_url = f"https://storage.googleapis.com/{bucket_name}/posts.json"
    print(f"Uploaded posts.json to {public_url}")
    return public_url


def main():
    username = os.environ.get("INSTAGRAM_USERNAME")
    bucket_name = os.environ.get("GCS_BUCKET")
    post_count = int(os.environ.get("POST_COUNT", "12"))
    login_user = os.environ.get("IG_LOGIN_USER")
    login_pass = os.environ.get("IG_LOGIN_PASS")
    session_id = os.environ.get("IG_SESSION_ID")

    if not username:
        print("ERROR: INSTAGRAM_USERNAME environment variable is required")
        sys.exit(1)

    if not bucket_name:
        print("ERROR: GCS_BUCKET environment variable is required")
        sys.exit(1)

    print(f"Starting scrape: {username} (last {post_count} posts)")

    try:
        data = scrape_profile(username, post_count, login_user, login_pass, session_id)
        print(f"Scraped {len(data['posts'])} posts")

        upload_to_gcs(bucket_name, data)
        print("Done!")

    except Exception as e:
        print(f"ERROR: {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()
