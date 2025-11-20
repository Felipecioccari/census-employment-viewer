
const FIRST_YEAR = Number(import.meta.env.VITE_FIRST_YEAR_QUARTERS) || 2020;
const LAST_YEAR =
  Number(import.meta.env.VITE_LAST_YEAR_QUARTERS) || 2023; // Update this when a new year of quarters is available.

export const buildQuarters = (
  firstYear: number = FIRST_YEAR,
  lastYear: number = LAST_YEAR
): string[] => {
  const quarters: string[] = [];

  for (let year = lastYear; year >= firstYear; year -= 1) {
    for (let quarter = 4; quarter >= 1; quarter -= 1) {
      quarters.push(`${year}-Q${quarter}`);
    }
  }

  return quarters;
};
