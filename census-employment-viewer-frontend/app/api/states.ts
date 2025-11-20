import type { StateOption } from "../data/states";
import { apiClient } from "./client";

type StatesResponse = {
  data?: StateOption[];
  message?: string;
};

export async function fetchStates(): Promise<StateOption[]> {
  const response = await apiClient.get<StatesResponse>("/api/states");
  return response.data.data ?? [];
}
