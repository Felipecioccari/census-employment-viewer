import axios from "axios";

export const API_BASE =
  import.meta.env.VITE_QWI_BASE_URL_API || "http://127.0.0.1:8000"

export const apiClient = axios.create({
  baseURL: API_BASE,
  timeout: 15000,
});

export function buildApiUrl(path: string): string {
  return `${API_BASE}${path}`;
}
