# SDS Mobile

Mobile app scaffold for `student` and `class teacher` accounts using Ionic Vue and Capacitor.

## Current scope

- Student login and home flow
- Class teacher login and home flow
- Role-aware routing for future expansion
- Shared Laravel API integration using bearer tokens

Unsupported roles can still authenticate but are sent to a placeholder screen so we can enable them later without restructuring the app.

## Setup

1. Copy `.env.example` to `.env` and adjust `VITE_API_BASE_URL`
2. Install dependencies
   `npm install`
3. Start the dev server
   `npm run dev`

## Android

1. Build the web app
   `npm run build`
2. Add Android platform once
   `npx cap add android`
3. Sync native files
   `npm run android:sync`
4. Open Android Studio
   `npm run android:open`

## Suggested next features

- Replace placeholder dashboards with real attendance, marks, and payments flows
- Move auth storage from `localStorage` to Capacitor Preferences
- Add push notifications, offline caching, and biometric login
