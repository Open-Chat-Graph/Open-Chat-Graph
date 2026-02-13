import { ListItem, ListItemText, Typography } from "@mui/material";

export default function EmptyListItem() {
  return (
    <ListItem>
      <ListItemText
        primary={
          <Typography textAlign="center" fontWeight="bold">
            一番乗りでコメントしてみよう
          </Typography>
        }
      />
    </ListItem>
  )
}