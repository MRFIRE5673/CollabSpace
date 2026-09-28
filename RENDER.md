# Connecting Render PostgreSQL Database to CollabSpace on Vercel ?

CollabSpace natively supports **Render PostgreSQL** for cloud multi-tenant database storage while Vercel hosts the application UI and PHP serverless functions.

---

## 1. Create a Free PostgreSQL Database on Render

1. Log in to [dashboard.render.com](https://dashboard.render.com).
2. Click **New +** ? **PostgreSQL**.
3. Name: `collabspace-db`
4. Database: `collabspace`
5. User: `collabspace_user`
6. Region: Select your preferred region.
7. Plan: **Free**.
8. Click **Create Database**.

---

## 2. Copy External Connection String

On your Render PostgreSQL dashboard:
- Under **Connections**, copy the **External Database URL**:
  - Example: `postgres://collabspace_user:PASSWORD@dpg-xxx-a.singapore-postgres.render.com/collabspace`

---

## 3. Add Connection String to Vercel

In your **Vercel Project Settings ? Environment Variables**:

- **Key**: `DATABASE_URL`
- **Value**: `postgres://collabspace_user:PASSWORD@dpg-xxx-a.singapore-postgres.render.com/collabspace`
- Click **Save**.

---

## 4. Automated Connection & Schema Setup

Once saved:
- Vercel automatically redeploys your web app.
- CollabSpace connects to Render PostgreSQL via SSL (`sslmode=require`) and initializes multi-tenant schema tables automatically!

