import type { EmploymentError, EmploymentRow } from "../types/employment";
import { apiClient } from "./client";

type EmploymentResponse = {
  data?: EmploymentRow[];
  errors?: EmploymentError[];
  hasErrors?: boolean;
  message?: string;
};

type EmploymentRequest = {
  quarter: string;
  states: string;
  breakdownSex?: boolean;
};

export async function fetchEmployments(params: EmploymentRequest) {
  const { quarter, states, breakdownSex } = params;
  const query = new URLSearchParams({
    quarter,
    states,
  });

  if (breakdownSex) {
    query.set("breakdownSex", "1");
  }

  const response = await apiClient.get<EmploymentResponse>(
    `/api/employments?${query.toString()}`,
  );

  return {
    rows: Array.isArray(response.data.data) ? response.data.data : [],
    errors: Array.isArray(response.data.errors) ? response.data.errors : [],
    hasErrors: Boolean(response.data.hasErrors),
    message: response.data.message,
  };
}
