#!/usr/bin/env bash
#
# Deploy the Instagram scraper to Google Cloud.
#
# Prerequisites:
#   - gcloud CLI installed and authenticated
#   - A GCP project selected (gcloud config set project PROJECT_ID)
#
# Usage:
#   ./deploy.sh <project-id> <instagram-username> [region]
#
# Example:
#   ./deploy.sh my-gcp-project myclient europe-west1

set -euo pipefail

PROJECT_ID="${1:?Usage: deploy.sh <project-id> <instagram-username> [region]}"
INSTAGRAM_USERNAME="${2:?Usage: deploy.sh <project-id> <instagram-username> [region]}"
REGION="${3:-europe-west1}"

BUCKET_NAME="${PROJECT_ID}-instagram-feed"
IMAGE_NAME="${REGION}-docker.pkg.dev/${PROJECT_ID}/instagram-scraper/scraper"
JOB_NAME="instagram-scraper"
SCHEDULER_NAME="instagram-scraper-daily"

echo "==> Deploying Instagram Scraper"
echo "    Project:   ${PROJECT_ID}"
echo "    Username:  ${INSTAGRAM_USERNAME}"
echo "    Region:    ${REGION}"
echo "    Bucket:    ${BUCKET_NAME}"
echo ""

# ------------------------------------------------------------------
# 1. Enable required APIs
# ------------------------------------------------------------------
echo "==> Enabling required APIs..."
gcloud services enable \
  run.googleapis.com \
  cloudscheduler.googleapis.com \
  storage.googleapis.com \
  artifactregistry.googleapis.com \
  --project="${PROJECT_ID}"

# ------------------------------------------------------------------
# 2. Create GCS bucket
# ------------------------------------------------------------------
echo "==> Creating GCS bucket: ${BUCKET_NAME}"
gcloud storage buckets create "gs://${BUCKET_NAME}" \
  --project="${PROJECT_ID}" \
  --location="${REGION}" \
  --uniform-bucket-level-access \
  2>/dev/null || echo "    Bucket already exists, skipping."

# Make bucket publicly readable
echo "==> Setting public read access..."
gcloud storage buckets add-iam-policy-binding "gs://${BUCKET_NAME}" \
  --member="allUsers" \
  --role="roles/storage.objectViewer"

# Set CORS policy
echo "==> Setting CORS policy..."
cat > /tmp/cors.json << 'CORS_EOF'
[
  {
    "origin": ["*"],
    "method": ["GET"],
    "responseHeader": ["Content-Type"],
    "maxAgeSeconds": 3600
  }
]
CORS_EOF
gcloud storage buckets update "gs://${BUCKET_NAME}" --cors-file=/tmp/cors.json
rm /tmp/cors.json

# ------------------------------------------------------------------
# 3. Create Artifact Registry repository
# ------------------------------------------------------------------
echo "==> Creating Artifact Registry repository..."
gcloud artifacts repositories create instagram-scraper \
  --repository-format=docker \
  --location="${REGION}" \
  --project="${PROJECT_ID}" \
  2>/dev/null || echo "    Repository already exists, skipping."

# ------------------------------------------------------------------
# 4. Build and push Docker image
# ------------------------------------------------------------------
echo "==> Building and pushing Docker image..."
cd "$(dirname "$0")/../scraper"
gcloud builds submit \
  --tag="${IMAGE_NAME}" \
  --project="${PROJECT_ID}"
cd -

# ------------------------------------------------------------------
# 5. Create Cloud Run Job
# ------------------------------------------------------------------
echo "==> Creating Cloud Run Job: ${JOB_NAME}"
gcloud run jobs create "${JOB_NAME}" \
  --image="${IMAGE_NAME}" \
  --region="${REGION}" \
  --project="${PROJECT_ID}" \
  --set-env-vars="INSTAGRAM_USERNAME=${INSTAGRAM_USERNAME},GCS_BUCKET=${BUCKET_NAME},POST_COUNT=12" \
  --memory="512Mi" \
  --task-timeout="300s" \
  --max-retries=1 \
  2>/dev/null || \
gcloud run jobs update "${JOB_NAME}" \
  --image="${IMAGE_NAME}" \
  --region="${REGION}" \
  --project="${PROJECT_ID}" \
  --set-env-vars="INSTAGRAM_USERNAME=${INSTAGRAM_USERNAME},GCS_BUCKET=${BUCKET_NAME},POST_COUNT=12" \
  --memory="512Mi" \
  --task-timeout="300s" \
  --max-retries=1

# ------------------------------------------------------------------
# 6. Create Cloud Scheduler job
# ------------------------------------------------------------------
echo "==> Creating Cloud Scheduler job: ${SCHEDULER_NAME}"

# Get the service account for Cloud Run
SA_EMAIL="$(gcloud iam service-accounts list \
  --project="${PROJECT_ID}" \
  --filter="displayName:Default compute service account" \
  --format="value(email)" \
  | head -1)"

if [ -z "${SA_EMAIL}" ]; then
  SA_EMAIL="${PROJECT_ID}@appspot.gserviceaccount.com"
fi

gcloud scheduler jobs create http "${SCHEDULER_NAME}" \
  --location="${REGION}" \
  --project="${PROJECT_ID}" \
  --schedule="0 0 * * *" \
  --time-zone="Europe/Copenhagen" \
  --uri="https://${REGION}-run.googleapis.com/apis/run.googleapis.com/v1/namespaces/${PROJECT_ID}/jobs/${JOB_NAME}:run" \
  --http-method=POST \
  --oauth-service-account-email="${SA_EMAIL}" \
  --attempt-deadline="600s" \
  2>/dev/null || echo "    Scheduler job already exists. Update manually if needed."

# ------------------------------------------------------------------
# Done
# ------------------------------------------------------------------
echo ""
echo "==> Deployment complete!"
echo ""
echo "    JSON feed URL: https://storage.googleapis.com/${BUCKET_NAME}/posts.json"
echo ""
echo "    To run the scraper manually:"
echo "      gcloud run jobs execute ${JOB_NAME} --region=${REGION} --project=${PROJECT_ID}"
echo ""
echo "    Configure this URL in WordPress:"
echo "      Settings > Instagram Feed > JSON Feed URL"
